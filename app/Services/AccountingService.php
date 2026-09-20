<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\ExpenseCategory;
use App\Enums\ExpenseStatus;
use App\Enums\FiscalYearStatus;
use App\Enums\JournalCode;
use App\Enums\PayrollStatus;
use App\Enums\SaleStatus;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Exceptions\AccountingException;
use App\Models\ChartAccount;
use App\Models\Expense;
use App\Models\FiscalYear;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Payroll;
use App\Models\Sale;
use App\Models\Transaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Génération des écritures comptables en partie double (SYSCOHADA) à partir des
 * événements métier de GESTAVI.
 *
 * Schéma d'écritures :
 *  - Vente (VE) quand la facture quitte le brouillon : 411 D TTC / 701 C HT / 4431 C TVA
 *    (annulation → extourne).
 *  - Dépense (AC) à la création : charge D HT (mapping catégorie) + 4452 D TVA / 401 C TTC
 *    (annulation → extourne). Les dépenses « salaires » liées à la paie ne sont pas
 *    comptabilisées ici (la validation du bulletin s'en charge).
 *  - Paie (PA) à la validation : 661 D brut, 664 D charges employeur / 421 C net,
 *    431 C CNPS (salarié + employeur), 447 C ITS.
 *  - Transaction de trésorerie (BQ ou CA) à sa création : compte de trésorerie du compte
 *    financier contre la contrepartie déduite de la catégorie (411 encaissement client,
 *    401 règlement fournisseur, 421 paiement de salaire, 585 transfert interne,
 *    101 ouverture, 471 ajustement, 411/401 remboursement).
 *  La trésorerie ne passe QUE par postTransaction : aucun double compte.
 */
class AccountingService
{
    public function __construct(private readonly AccountingMapper $mapper, private readonly NumberSequence $sequence) {}

    // ─── PÉRIODES ───────────────────────────────────────────────────────────

    /**
     * Refuse toute écriture datée dans un exercice clôturé.
     */
    public function assertPeriodOpen(?int $organisationId, mixed $date): void
    {
        if ($organisationId === null || $date === null) {
            return;
        }

        $closed = FiscalYear::query()
            ->where('organisation_id', $organisationId)
            ->where('status', FiscalYearStatus::Closed->value)
            ->covering($date)
            ->first();

        if ($closed) {
            throw AccountingException::periodClosed(Carbon::parse($date)->format('d/m/Y'), $closed->label);
        }
    }

    public function fiscalYearFor(?int $organisationId, mixed $date): ?FiscalYear
    {
        if ($organisationId === null || $date === null) {
            return null;
        }

        return FiscalYear::query()->where('organisation_id', $organisationId)->covering($date)->first();
    }

    // ─── ÉCRITURE GÉNÉRIQUE ─────────────────────────────────────────────────

    /**
     * Enregistre une écriture équilibrée.
     *
     * @param  list<array{code: string, debit?: float, credit?: float, third_party?: string|null, label?: string|null}>  $lines
     * @param  array<string, mixed>  $attributes  organisation_id (requis), farm_id, entry_date, journal, label, reference_*
     */
    public function record(array $attributes, array $lines): JournalEntry
    {
        $lines = array_values(array_filter($lines, fn (array $line): bool => round((float) ($line['debit'] ?? 0), 2) > 0 || round((float) ($line['credit'] ?? 0), 2) > 0));

        $debit = round(array_sum(array_map(fn (array $l): float => (float) ($l['debit'] ?? 0), $lines)), 2);
        $credit = round(array_sum(array_map(fn (array $l): float => (float) ($l['credit'] ?? 0), $lines)), 2);

        if (abs($debit - $credit) >= 0.01) {
            throw AccountingException::unbalancedEntry($debit, $credit);
        }

        $organisationId = (int) $attributes['organisation_id'];
        $date = Carbon::parse($attributes['entry_date'] ?? now());

        $this->assertPeriodOpen($organisationId, $date);

        return DB::transaction(function () use ($attributes, $lines, $organisationId, $date): JournalEntry {
            $entry = JournalEntry::query()->create(array_merge($attributes, [
                'entry_date' => $date->toDateString(),
                'fiscal_year_id' => $this->fiscalYearFor($organisationId, $date)?->id,
                'entry_number' => $this->sequence->nextFormatted(
                    prefix: 'ECR-',
                    key: 'journal_entry',
                    scopeId: $organisationId,
                    year: $date->year,
                    pad: 6,
                    seedUsing: fn (): int => $this->sequence->maxSuffix('journal_entries', 'entry_number', "ECR-{$date->year}-", ['organisation_id' => $organisationId]),
                ),
                'user_id' => $attributes['user_id'] ?? Auth::id(),
                'is_generated' => $attributes['is_generated'] ?? true,
            ]));

            foreach ($lines as $position => $line) {
                JournalLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'organisation_id' => $organisationId,
                    'farm_id' => $line['farm_id'] ?? $entry->farm_id,
                    'entry_date' => $date->toDateString(),
                    'chart_account_code' => $line['code'],
                    'chart_account_label' => ChartAccount::labelFor($line['code'], $organisationId),
                    'debit' => round((float) ($line['debit'] ?? 0), 2),
                    'credit' => round((float) ($line['credit'] ?? 0), 2),
                    'third_party' => $line['third_party'] ?? null,
                    'label' => $line['label'] ?? $entry->label,
                    'position' => $position,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Extourne une écriture (débits ↔ crédits), datée du jour de l'annulation.
     */
    public function reverse(JournalEntry $entry, ?string $label = null, mixed $date = null): JournalEntry
    {
        $lines = $entry->lines->map(fn (JournalLine $line): array => [
            'code' => $line->chart_account_code,
            'debit' => (float) $line->credit,
            'credit' => (float) $line->debit,
            'third_party' => $line->third_party,
            'farm_id' => $line->farm_id,
            'label' => $label ?? 'Extourne '.$entry->entry_number,
        ])->all();

        return $this->record([
            'organisation_id' => $entry->organisation_id,
            'farm_id' => $entry->farm_id,
            'entry_date' => $date ?? now(),
            'journal' => $entry->journal,
            'label' => $label ?? 'Extourne '.$entry->entry_number.' — '.$entry->label,
            'reference_type' => $entry->reference_type,
            'reference_id' => $entry->reference_id,
            'reference_number' => $entry->reference_number,
            'is_reversal' => true,
            'reversed_entry_id' => $entry->id,
        ], $lines);
    }

    private function generatedEntryFor(string $type, int $id): ?JournalEntry
    {
        return JournalEntry::query()
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->where('is_reversal', false)
            ->whereDoesntHave('reversal')
            ->first();
    }

    private function alreadyPosted(string $type, int $id): bool
    {
        return JournalEntry::query()
            ->where('reference_type', $type)
            ->where('reference_id', $id)
            ->where('is_reversal', false)
            ->exists();
    }

    // ─── TRÉSORERIE ─────────────────────────────────────────────────────────

    public function postTransaction(Transaction $transaction): ?JournalEntry
    {
        if ($this->alreadyPosted(Transaction::class, $transaction->id) || (float) $transaction->amount <= 0) {
            return null;
        }

        $account = $transaction->account;

        if (! $account) {
            return null;
        }

        $organisationId = (int) $transaction->organisation_id;
        $treasury = $this->mapper->treasuryCode($account->type instanceof AccountType ? $account->type : AccountType::from((string) $account->type), $organisationId);
        [$counterpart, $thirdParty] = $this->counterpartFor($transaction, $organisationId);
        $amount = (float) $transaction->amount;
        $isCredit = $transaction->type === TransactionType::Credit;

        $journal = $account->type === AccountType::Cash ? JournalCode::Cash : JournalCode::Bank;

        return $this->record([
            'organisation_id' => $organisationId,
            'farm_id' => $transaction->farm_id,
            'entry_date' => $transaction->transaction_date,
            'journal' => $journal,
            'label' => $transaction->description ?: ($transaction->category?->getLabel() ?? 'Mouvement de trésorerie'),
            'reference_type' => Transaction::class,
            'reference_id' => $transaction->id,
            'reference_number' => $transaction->reference_number,
            'user_id' => $transaction->user_id,
        ], [
            ['code' => $treasury, 'debit' => $isCredit ? $amount : 0, 'credit' => $isCredit ? 0 : $amount, 'third_party' => $account->name],
            ['code' => $counterpart, 'debit' => $isCredit ? 0 : $amount, 'credit' => $isCredit ? $amount : 0, 'third_party' => $thirdParty],
        ]);
    }

    /**
     * Compte de contrepartie et tiers d'une transaction selon sa catégorie.
     *
     * @return array{0: string, 1: string|null}
     */
    private function counterpartFor(Transaction $transaction, int $organisationId): array
    {
        $reference = $transaction->reference_type && $transaction->reference_id
            ? $transaction->reference()->first()
            : null;

        return match ($transaction->category) {
            TransactionCategory::Payment, TransactionCategory::Sale => [
                $this->mapper->codeFor('sale.customer', $organisationId),
                $reference instanceof Sale ? $reference->customer?->name : null,
            ],
            TransactionCategory::Expense => $reference instanceof Expense
                ? [$this->mapper->codeFor('expense.supplier', $organisationId), $reference->supplier?->name]
                : [$this->mapper->codeFor('expense.other', $organisationId), null],
            TransactionCategory::Payroll => [
                $this->mapper->codeFor('payroll.net', $organisationId),
                $reference instanceof Expense ? $reference->description : null,
            ],
            TransactionCategory::TransferIn, TransactionCategory::TransferOut => [$this->mapper->codeFor('transfer.internal', $organisationId), null],
            TransactionCategory::Opening => [$this->mapper->codeFor('treasury.opening', $organisationId), null],
            TransactionCategory::Adjustment => [$this->mapper->codeFor('treasury.adjustment', $organisationId), null],
            TransactionCategory::Refund => $transaction->type === TransactionType::Debit
                ? [$this->mapper->codeFor('sale.customer', $organisationId), $reference instanceof Sale ? $reference->customer?->name : null]
                : [$this->mapper->codeFor('expense.supplier', $organisationId), null],
            default => [$this->mapper->codeFor('treasury.other', $organisationId), null],
        };
    }

    // ─── VENTES ─────────────────────────────────────────────────────────────

    public function postSale(Sale $sale): ?JournalEntry
    {
        if ($sale->status === SaleStatus::Draft || $sale->status === SaleStatus::Cancelled) {
            return null;
        }

        if ($this->alreadyPosted(Sale::class, $sale->id) || (float) $sale->total <= 0) {
            return null;
        }

        $organisationId = (int) $sale->organisation_id;
        $total = (float) $sale->total;
        $tax = (float) $sale->tax_amount;
        $customer = $sale->customer?->name;

        return $this->record([
            'organisation_id' => $organisationId,
            'farm_id' => $sale->farm_id,
            'entry_date' => $sale->sale_date,
            'journal' => JournalCode::Sales,
            'label' => 'Facture '.$sale->invoice_number.($customer ? ' — '.$customer : ''),
            'reference_type' => Sale::class,
            'reference_id' => $sale->id,
            'reference_number' => $sale->invoice_number,
            'user_id' => $sale->user_id,
        ], [
            ['code' => $this->mapper->codeFor('sale.customer', $organisationId), 'debit' => $total, 'third_party' => $customer],
            ['code' => $this->mapper->codeFor('sale.revenue', $organisationId), 'credit' => $total - $tax],
            ['code' => $this->mapper->codeFor('sale.tax', $organisationId), 'credit' => $tax],
        ]);
    }

    public function reverseSale(Sale $sale): ?JournalEntry
    {
        $entry = $this->generatedEntryFor(Sale::class, $sale->id);

        return $entry ? $this->reverse($entry, 'Annulation facture '.$sale->invoice_number) : null;
    }

    // ─── DÉPENSES ───────────────────────────────────────────────────────────

    public function postExpense(Expense $expense): ?JournalEntry
    {
        if ($expense->status === ExpenseStatus::Cancelled || (float) $expense->amount <= 0) {
            return null;
        }

        // Les salaires payés via la paie sont comptabilisés à la validation du bulletin
        if ($expense->category === ExpenseCategory::Salaries && Payroll::query()->where('expense_id', $expense->id)->exists()) {
            return null;
        }

        if ($this->alreadyPosted(Expense::class, $expense->id)) {
            return null;
        }

        $organisationId = (int) $expense->organisation_id;
        $total = (float) $expense->amount;
        $tax = min($total, (float) ($expense->tax_amount ?? 0));
        $supplier = $expense->supplier?->name;

        return $this->record([
            'organisation_id' => $organisationId,
            'farm_id' => $expense->farm_id,
            'entry_date' => $expense->expense_date,
            'journal' => JournalCode::Purchases,
            'label' => 'Dépense '.$expense->expense_number.' — '.$expense->description,
            'reference_type' => Expense::class,
            'reference_id' => $expense->id,
            'reference_number' => $expense->expense_number,
            'user_id' => $expense->user_id,
        ], [
            ['code' => $this->mapper->expenseCode($expense->category, $organisationId), 'debit' => $total - $tax],
            ['code' => $this->mapper->codeFor('expense.tax', $organisationId), 'debit' => $tax],
            ['code' => $this->mapper->codeFor('expense.supplier', $organisationId), 'credit' => $total, 'third_party' => $supplier],
        ]);
    }

    public function reverseExpense(Expense $expense): ?JournalEntry
    {
        $entry = $this->generatedEntryFor(Expense::class, $expense->id);

        return $entry ? $this->reverse($entry, 'Annulation dépense '.$expense->expense_number) : null;
    }

    // ─── PAIE ───────────────────────────────────────────────────────────────

    public function postPayroll(Payroll $payroll): ?JournalEntry
    {
        if (! in_array($payroll->status, [PayrollStatus::Validated, PayrollStatus::Paid], true)) {
            return null;
        }

        if ($this->alreadyPosted(Payroll::class, $payroll->id) || (float) $payroll->gross_salary <= 0) {
            return null;
        }

        $organisationId = (int) $payroll->organisation_id;
        $gross = (float) $payroll->gross_salary;
        $employer = (float) $payroll->cnps_employer;
        $cnpsEmployee = (float) $payroll->cnps_employee;
        $its = (float) $payroll->its;
        $other = (float) ($payroll->other_deductions ?? 0);
        $net = (float) $payroll->net_salary;
        $employee = $payroll->employee?->full_name;

        // Bulletin sans net calculé (saisie partielle) : net = brut − retenues salariales
        if ($net <= 0) {
            $net = max(0, $gross - $cnpsEmployee - $its - $other);
        }

        // Équilibre : 661 + 664 = 421 (net) + 431 (CNPS salarié + employeur) + 447 (ITS) + 471 (autres retenues)
        // Un écart d'arrondi ou de saisie est porté en 471 plutôt que de bloquer la validation.
        $residual = round(($gross + $employer) - ($net + $cnpsEmployee + $employer + $its + $other), 2);
        $other += $residual;

        $date = $payroll->validated_at ?? Carbon::create($payroll->period_year, $payroll->period_month)->endOfMonth();

        return $this->record([
            'organisation_id' => $organisationId,
            'farm_id' => $payroll->farm_id,
            'entry_date' => $date,
            'journal' => JournalCode::Payroll,
            'label' => 'Bulletin '.$payroll->getReference().($employee ? ' — '.$employee : ''),
            'reference_type' => Payroll::class,
            'reference_id' => $payroll->id,
            'reference_number' => $payroll->getReference(),
            'user_id' => $payroll->validated_by,
        ], [
            ['code' => $this->mapper->codeFor('payroll.gross', $organisationId), 'debit' => $gross, 'third_party' => $employee],
            ['code' => $this->mapper->codeFor('payroll.employer_contributions', $organisationId), 'debit' => $employer],
            ['code' => $this->mapper->codeFor('payroll.net', $organisationId), 'credit' => $net, 'third_party' => $employee],
            ['code' => $this->mapper->codeFor('payroll.cnps', $organisationId), 'credit' => $cnpsEmployee + $employer],
            ['code' => $this->mapper->codeFor('payroll.its', $organisationId), 'credit' => $its],
            ['code' => $this->mapper->codeFor('treasury.adjustment', $organisationId), 'credit' => max(0, $other), 'debit' => max(0, -$other)],
        ]);
    }

    // ─── CLÔTURE ────────────────────────────────────────────────────────────

    /**
     * Clôture un exercice : vérifie l'équilibre des écritures, solde les classes 6 et 7
     * vers 131 (résultat net) par une écriture OD datée du dernier jour, passe en « clôturé ».
     */
    public function closeFiscalYear(FiscalYear $fiscalYear, ?int $userId = null): FiscalYear
    {
        if ($fiscalYear->isClosed()) {
            throw AccountingException::fiscalYearAlreadyClosed($fiscalYear->label);
        }

        $entries = JournalEntry::query()
            ->where('organisation_id', $fiscalYear->organisation_id)
            ->forPeriod($fiscalYear->starts_on, $fiscalYear->ends_on)
            ->get();

        $unbalanced = $entries->filter(fn (JournalEntry $entry): bool => ! $entry->isBalanced())->count();

        if ($unbalanced > 0) {
            throw AccountingException::fiscalYearHasUnbalancedEntries($fiscalYear->label, $unbalanced);
        }

        return DB::transaction(function () use ($fiscalYear, $userId): FiscalYear {
            $balances = JournalLine::query()
                ->where('organisation_id', $fiscalYear->organisation_id)
                ->whereBetween('entry_date', [$fiscalYear->starts_on->toDateString(), $fiscalYear->ends_on->toDateString()])
                ->whereRaw("LEFT(chart_account_code, 1) IN ('6', '7')")
                ->selectRaw('chart_account_code, SUM(debit) AS debit, SUM(credit) AS credit')
                ->groupBy('chart_account_code')
                ->get();

            $lines = [];
            $result = 0.0;

            foreach ($balances as $balance) {
                $net = (float) $balance->debit - (float) $balance->credit;

                if (abs($net) < 0.01) {
                    continue;
                }

                // Solder le compte : un solde débiteur (charge) est crédité, un solde créditeur (produit) est débité
                $lines[] = $net > 0
                    ? ['code' => $balance->chart_account_code, 'credit' => $net]
                    : ['code' => $balance->chart_account_code, 'debit' => -$net];

                $result += -$net; // produits − charges
            }

            $resultCode = $this->mapper->codeFor('closing.result', $fiscalYear->organisation_id);

            if (abs($result) >= 0.01) {
                $lines[] = $result > 0
                    ? ['code' => $resultCode, 'credit' => $result]
                    : ['code' => $resultCode, 'debit' => -$result];
            }

            if ($lines !== []) {
                $this->record([
                    'organisation_id' => $fiscalYear->organisation_id,
                    'entry_date' => $fiscalYear->ends_on,
                    'journal' => JournalCode::Miscellaneous,
                    'label' => 'Clôture exercice '.$fiscalYear->label.' — détermination du résultat',
                    'reference_type' => FiscalYear::class,
                    'reference_id' => $fiscalYear->id,
                    'reference_number' => $fiscalYear->label,
                    'user_id' => $userId,
                    'is_generated' => true,
                ], $lines);
            }

            $fiscalYear->update([
                'status' => FiscalYearStatus::Closed,
                'closed_at' => now(),
                'closed_by' => $userId,
                'net_result' => round($result, 2),
            ]);

            return $fiscalYear->fresh();
        });
    }

    // ─── RECONSTRUCTION ─────────────────────────────────────────────────────

    /**
     * Supprime les écritures auto-générées d'une organisation et les rejoue depuis
     * les faits (transactions, ventes, dépenses, bulletins) par ordre de date.
     * Idempotent : rejouer deux fois donne le même journal.
     *
     * @return array<string, int>
     */
    public function rebuild(int $organisationId): array
    {
        return DB::transaction(function () use ($organisationId): array {
            JournalEntry::query()
                ->where('organisation_id', $organisationId)
                ->where('is_generated', true)
                ->where('reference_type', '!=', FiscalYear::class)
                ->delete();

            $counts = ['sales' => 0, 'expenses' => 0, 'payrolls' => 0, 'transactions' => 0];

            Sale::query()->where('organisation_id', $organisationId)->whereNotIn('status', [SaleStatus::Draft->value])->orderBy('sale_date')->orderBy('id')
                ->each(function (Sale $sale) use (&$counts): void {
                    if ($sale->status === SaleStatus::Cancelled) {
                        // Une facture annulée a existé puis été extournée : on rejoue les deux si elle a un total
                        $status = $sale->status;
                        $sale->setAttribute('status', SaleStatus::Pending);
                        $entry = $this->postSale($sale);
                        $sale->setAttribute('status', $status);
                        if ($entry) {
                            $this->reverse($entry, 'Annulation facture '.$sale->invoice_number, $sale->updated_at);
                            $counts['sales']++;
                        }

                        return;
                    }

                    $counts['sales'] += $this->postSale($sale) ? 1 : 0;
                });

            Expense::query()->where('organisation_id', $organisationId)->orderBy('expense_date')->orderBy('id')
                ->each(function (Expense $expense) use (&$counts): void {
                    if ($expense->status === ExpenseStatus::Cancelled) {
                        $expense->setAttribute('status', ExpenseStatus::Unpaid);
                        $entry = $this->postExpense($expense);
                        $expense->setAttribute('status', ExpenseStatus::Cancelled);
                        if ($entry) {
                            $this->reverse($entry, 'Annulation dépense '.$expense->expense_number, $expense->updated_at);
                            $counts['expenses']++;
                        }

                        return;
                    }

                    $counts['expenses'] += $this->postExpense($expense) ? 1 : 0;
                });

            Payroll::query()->where('organisation_id', $organisationId)->whereIn('status', [PayrollStatus::Validated->value, PayrollStatus::Paid->value])->orderBy('period_year')->orderBy('period_month')->orderBy('id')
                ->each(function (Payroll $payroll) use (&$counts): void {
                    $counts['payrolls'] += $this->postPayroll($payroll) ? 1 : 0;
                });

            Transaction::query()->where('organisation_id', $organisationId)->orderBy('transaction_date')->orderBy('id')
                ->each(function (Transaction $transaction) use (&$counts): void {
                    $counts['transactions'] += $this->postTransaction($transaction) ? 1 : 0;
                });

            return $counts;
        });
    }

    // ─── ÉTATS ──────────────────────────────────────────────────────────────

    /**
     * Balance des comptes : totaux débit/crédit et solde par compte sur une période.
     *
     * @return \Illuminate\Support\Collection<int, object{chart_account_code: string, chart_account_label: string, debit: string, credit: string}>
     */
    public function trialBalance(int $organisationId, CarbonInterface $from, CarbonInterface $to, ?int $farmId = null): \Illuminate\Support\Collection
    {
        return JournalLine::query()
            ->where('organisation_id', $organisationId)
            ->when($farmId, fn ($q) => $q->where('farm_id', $farmId))
            ->whereBetween('entry_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('chart_account_code, MAX(chart_account_label) AS chart_account_label, SUM(debit) AS debit, SUM(credit) AS credit')
            ->groupBy('chart_account_code')
            ->orderBy('chart_account_code')
            ->get();
    }
}
