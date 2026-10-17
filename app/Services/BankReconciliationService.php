<?php

namespace App\Services;

use App\Enums\BankReconciliationStatus;
use App\Enums\BankStatementLineStatus;
use App\Enums\TransactionType;
use App\Exceptions\BankReconciliationException;
use App\Models\Account;
use App\Models\BankReconciliation;
use App\Models\BankStatementLine;
use App\Models\Transaction;
use App\Services\Imports\SpreadsheetReader;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement d'un compte avec un relevé : ouverture, import des lignes du relevé (Excel / CSV),
 * correspondance automatique montant + date (± quelques jours) + référence, pointage manuel,
 * clôture quand le solde pointé égale le solde du relevé.
 */
class BankReconciliationService
{
    /** Écart toléré entre solde du relevé et solde pointé (arrondis). */
    public const float BALANCE_TOLERANCE = 0.01;

    /** Décalage de date admis entre une ligne de relevé et la transaction correspondante. */
    public const int DEFAULT_DAY_TOLERANCE = 3;

    /** En-têtes reconnus (normalisés par SpreadsheetReader::normalizeHeader), par colonne attendue. */
    public const array HEADER_ALIASES = [
        'date' => ['date', 'date_operation', 'date_d_operation', 'date_valeur', 'date_de_valeur', 'date_op', 'date_compta', 'date_comptable', 'jour'],
        'label' => ['libelle', 'libelle_operation', 'libelle_de_l_operation', 'description', 'intitule', 'label', 'designation', 'operation', 'motif', 'nature'],
        'amount' => ['montant', 'amount', 'montant_xof', 'montant_fcfa', 'valeur', 'solde_mouvement'],
        'debit' => ['debit', 'retrait', 'retraits', 'sortie', 'sorties', 'montant_debit', 'debit_xof', 'depense'],
        'credit' => ['credit', 'depot', 'depots', 'entree', 'entrees', 'versement', 'montant_credit', 'credit_xof', 'recette'],
        'reference' => ['reference', 'ref', 'numero', 'n', 'no', 'piece', 'n_piece', 'numero_piece', 'id_transaction', 'transaction_id', 'identifiant'],
    ];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    // ─── OUVERTURE / SYNTHÈSE ────────────────────────────────────────────────

    /**
     * Ouvre un rapprochement sur un compte ; un seul brouillon à la fois par compte.
     */
    public function start(
        Account $account,
        CarbonInterface|string $statementDate,
        float $statementBalance,
        ?string $notes = null,
        ?int $userId = null,
    ): BankReconciliation {
        if ($account->bankReconciliations()->draft()->exists()) {
            throw BankReconciliationException::alreadyOpen($account->name);
        }

        return BankReconciliation::query()->create([
            'organisation_id' => $account->organisation_id,
            'farm_id' => $account->farm_id,
            'account_id' => $account->id,
            'statement_date' => Carbon::parse($statementDate)->toDateString(),
            'statement_balance' => $statementBalance,
            'status' => BankReconciliationStatus::Draft,
            'notes' => $notes,
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    /**
     * Solde pointé à la date du relevé : solde initial (s'il n'est pas déjà porté par la transaction
     * d'ouverture) + crédits − débits des transactions rapprochées datées au plus tard du relevé,
     * ou pointées dans ce rapprochement.
     */
    public function reconciledBalance(BankReconciliation $reconciliation): float
    {
        $account = $reconciliation->account;

        $query = $account->transactions()
            ->where('is_reconciled', true)
            ->where(fn (Builder $q): Builder => $q
                ->whereDate('transaction_date', '<=', $reconciliation->statement_date->toDateString())
                ->orWhere('bank_reconciliation_id', $reconciliation->id));

        $credits = (float) (clone $query)->where('type', TransactionType::Credit)->sum('amount');
        $debits = (float) (clone $query)->where('type', TransactionType::Debit)->sum('amount');
        $initial = $account->carriesOpeningTransaction() ? 0.0 : (float) $account->initial_balance;

        return round($initial + $credits - $debits, 2);
    }

    /**
     * @return array{
     *     statement_balance: float,
     *     reconciled_balance: float,
     *     difference: float,
     *     is_balanced: bool,
     *     pending_count: int,
     *     pending_amount: float,
     *     reconciled_count: int,
     *     lines_total: int,
     *     lines_matched: int,
     *     lines_unmatched: int,
     *     lines_ignored: int,
     * }
     */
    public function summary(BankReconciliation $reconciliation): array
    {
        $statement = (float) $reconciliation->statement_balance;
        $reconciled = $this->reconciledBalance($reconciliation);
        $difference = round($statement - $reconciled, 2);

        $pending = $reconciliation->candidateTransactions()->where('transactions.is_reconciled', false);
        $pendingAmount = (float) (clone $pending)->where('type', TransactionType::Credit)->sum('amount')
            - (float) (clone $pending)->where('type', TransactionType::Debit)->sum('amount');

        $lines = $reconciliation->lines()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'statement_balance' => $statement,
            'reconciled_balance' => $reconciled,
            'difference' => $difference,
            'is_balanced' => abs($difference) < self::BALANCE_TOLERANCE,
            'pending_count' => (clone $pending)->count(),
            'pending_amount' => round($pendingAmount, 2),
            'reconciled_count' => $reconciliation->transactions()->count(),
            'lines_total' => (int) $lines->sum(),
            'lines_matched' => (int) ($lines[BankStatementLineStatus::Matched->value] ?? 0),
            'lines_unmatched' => (int) ($lines[BankStatementLineStatus::Unmatched->value] ?? 0),
            'lines_ignored' => (int) ($lines[BankStatementLineStatus::Ignored->value] ?? 0),
        ];
    }

    // ─── POINTAGE ────────────────────────────────────────────────────────────

    /**
     * Pointe (ou dépointe) des transactions du compte dans ce rapprochement.
     *
     * @param  iterable<int>  $transactionIds
     * @return int Nombre de transactions modifiées
     */
    public function mark(BankReconciliation $reconciliation, iterable $transactionIds, bool $reconciled = true): int
    {
        $this->assertDraft($reconciliation);

        $ids = collect($transactionIds)->map(fn (mixed $id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($reconciliation, $ids, $reconciled): int {
            $transactions = Transaction::query()->whereKey($ids)->get();
            $count = 0;

            foreach ($transactions as $transaction) {
                if ((int) $transaction->account_id !== (int) $reconciliation->account_id) {
                    throw BankReconciliationException::foreignTransaction($transaction->id);
                }

                if ($reconciled) {
                    if ($transaction->is_reconciled && (int) $transaction->bank_reconciliation_id !== (int) $reconciliation->id) {
                        throw BankReconciliationException::transactionAlreadyReconciled($transaction->id);
                    }

                    if ($transaction->is_reconciled) {
                        continue;
                    }

                    $transaction->reconcile($reconciliation);
                    $count++;

                    continue;
                }

                if (! $transaction->is_reconciled) {
                    continue;
                }

                if ((int) $transaction->bank_reconciliation_id !== (int) $reconciliation->id) {
                    throw BankReconciliationException::transactionAlreadyReconciled($transaction->id);
                }

                // Une ligne de relevé associée à cette transaction redevient à rapprocher
                $reconciliation->lines()
                    ->where('matched_transaction_id', $transaction->id)
                    ->update(['matched_transaction_id' => null, 'status' => BankStatementLineStatus::Unmatched->value]);

                $transaction->unreconcile();
                $count++;
            }

            return $count;
        });
    }

    // ─── RELEVÉ IMPORTÉ ──────────────────────────────────────────────────────

    /**
     * Importe les lignes d'un relevé (xlsx / csv). Colonnes reconnues : date, libellé, montant signé
     * ou débit / crédit, référence. Les lignes identiques (date, montant, libellé) déjà présentes sont
     * ignorées, ce qui permet de réimporter un relevé complété.
     *
     * @return array{imported: int, duplicates: int, errors: list<string>}
     */
    public function importStatement(BankReconciliation $reconciliation, string $path, ?string $fileName = null): array
    {
        $this->assertDraft($reconciliation);

        $sheet = $this->reader->read($path);
        $columns = $this->resolveColumns($sheet['headers']);

        $existing = $reconciliation->lines()->get()
            ->map(fn (BankStatementLine $line): string => $this->lineFingerprint($line->line_date, (float) $line->amount, $line->label))
            ->flip();

        $imported = 0;
        $duplicates = 0;
        $errors = [];
        $rows = [];

        foreach ($sheet['rows'] as ['line' => $lineNumber, 'values' => $values]) {
            $date = $this->parseDate($values[$columns['date']] ?? null);

            if ($date === null) {
                $errors[] = "Ligne {$lineNumber} : date illisible.";

                continue;
            }

            $amount = $this->resolveAmount($values, $columns);

            if ($amount === null) {
                $errors[] = "Ligne {$lineNumber} : montant illisible ou nul.";

                continue;
            }

            $label = trim((string) ($values[$columns['label']] ?? '')) ?: 'Opération du '.$date->format('d/m/Y');
            $fingerprint = $this->lineFingerprint($date, $amount, $label);

            if ($existing->has($fingerprint)) {
                $duplicates++;

                continue;
            }

            $existing->put($fingerprint, true);

            $rows[] = [
                'bank_reconciliation_id' => $reconciliation->id,
                'line_date' => $date->toDateString(),
                'label' => mb_substr($label, 0, 255),
                'amount' => $amount,
                'reference' => isset($columns['reference']) ? mb_substr(trim((string) ($values[$columns['reference']] ?? '')), 0, 100) ?: null : null,
                'status' => BankStatementLineStatus::Unmatched->value,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $imported++;
        }

        DB::transaction(function () use ($reconciliation, $rows, $fileName): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                BankStatementLine::query()->insert($chunk);
            }

            if ($fileName !== null) {
                $reconciliation->update(['statement_file' => mb_substr($fileName, 0, 255)]);
            }
        });

        return ['imported' => $imported, 'duplicates' => $duplicates, 'errors' => $errors];
    }

    /**
     * Associe automatiquement chaque ligne à rapprocher à une transaction non pointée de même sens
     * et de même montant, datée à ± $dayTolerance jours ; à montants égaux, la référence commune
     * puis la date la plus proche départagent. Chaque transaction n'est prise qu'une fois.
     *
     * @return int Nombre de lignes rapprochées
     */
    public function autoMatch(BankReconciliation $reconciliation, int $dayTolerance = self::DEFAULT_DAY_TOLERANCE): int
    {
        $this->assertDraft($reconciliation);

        $lines = $reconciliation->lines()->where('status', BankStatementLineStatus::Unmatched->value)->orderBy('line_date')->get();

        if ($lines->isEmpty()) {
            return 0;
        }

        $from = $lines->min('line_date')->copy()->subDays($dayTolerance)->toDateString();
        $to = $lines->max('line_date')->copy()->addDays($dayTolerance)->toDateString();

        /** @var Collection<int, Transaction> $candidates */
        $candidates = $reconciliation->account->transactions()
            ->where('is_reconciled', false)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->get();

        $matched = 0;

        DB::transaction(function () use ($reconciliation, $lines, $candidates, $dayTolerance, &$matched): void {
            $used = [];

            foreach ($lines as $line) {
                $best = $candidates
                    ->reject(fn (Transaction $t): bool => isset($used[$t->id]))
                    ->filter(fn (Transaction $t): bool => $this->isCompatible($line, $t, $dayTolerance))
                    ->sortBy(fn (Transaction $t): array => [
                        $this->sameReference($line, $t) ? 0 : 1,
                        abs($t->transaction_date->diffInDays($line->line_date, false)),
                        $t->id,
                    ])
                    ->first();

                if (! $best instanceof Transaction) {
                    continue;
                }

                $used[$best->id] = true;
                $this->attach($reconciliation, $line, $best);
                $matched++;
            }
        });

        return $matched;
    }

    /** Associe manuellement une ligne du relevé à une transaction du compte, qui est pointée. */
    public function matchLine(BankStatementLine $line, Transaction $transaction): void
    {
        $reconciliation = $line->reconciliation;
        $this->assertDraft($reconciliation);

        if ($line->isMatched()) {
            throw BankReconciliationException::lineAlreadyMatched();
        }

        if ((int) $transaction->account_id !== (int) $reconciliation->account_id) {
            throw BankReconciliationException::foreignTransaction($transaction->id);
        }

        if ($transaction->type !== $line->expectedTransactionType() || abs((float) $transaction->amount - $line->absoluteAmount()) >= self::BALANCE_TOLERANCE) {
            throw BankReconciliationException::amountMismatch((float) $line->amount, (float) $transaction->amount);
        }

        if ($transaction->is_reconciled && (int) $transaction->bank_reconciliation_id !== (int) $reconciliation->id) {
            throw BankReconciliationException::transactionAlreadyReconciled($transaction->id);
        }

        DB::transaction(fn () => $this->attach($reconciliation, $line, $transaction));
    }

    /** Dissocie une ligne : la transaction redevient à pointer. */
    public function unmatchLine(BankStatementLine $line): void
    {
        $reconciliation = $line->reconciliation;
        $this->assertDraft($reconciliation);

        DB::transaction(function () use ($line): void {
            $transaction = $line->matchedTransaction;

            $line->update(['status' => BankStatementLineStatus::Unmatched, 'matched_transaction_id' => null]);

            if ($transaction instanceof Transaction && $transaction->is_reconciled) {
                $transaction->unreconcile();
            }
        });
    }

    /** Écarte une ligne du rapprochement (frais déjà saisis autrement, doublon du relevé…). */
    public function ignoreLine(BankStatementLine $line): void
    {
        $this->assertDraft($line->reconciliation);

        if ($line->isMatched()) {
            throw BankReconciliationException::lineAlreadyMatched();
        }

        $line->update(['status' => BankStatementLineStatus::Ignored]);
    }

    /** Remet une ligne ignorée à rapprocher. */
    public function restoreLine(BankStatementLine $line): void
    {
        $this->assertDraft($line->reconciliation);

        if ($line->isIgnored()) {
            $line->update(['status' => BankStatementLineStatus::Unmatched]);
        }
    }

    // ─── CLÔTURE ─────────────────────────────────────────────────────────────

    /**
     * Clôture le rapprochement : refusé tant que le solde pointé diffère du solde du relevé.
     */
    public function complete(BankReconciliation $reconciliation, ?int $userId = null): BankReconciliation
    {
        $this->assertDraft($reconciliation);

        $difference = round((float) $reconciliation->statement_balance - $this->reconciledBalance($reconciliation), 2);

        if (abs($difference) >= self::BALANCE_TOLERANCE) {
            throw BankReconciliationException::unbalanced($difference);
        }

        $reconciliation->update([
            'status' => BankReconciliationStatus::Completed,
            'completed_at' => now(),
            'completed_by' => $userId ?? auth()->id(),
        ]);

        return $reconciliation->refresh();
    }

    /**
     * Transactions proposables pour une ligne : non pointées, de même sens et de même montant,
     * triées par proximité de date.
     *
     * @return Collection<int, Transaction>
     */
    public function suggestionsFor(BankStatementLine $line, int $dayWindow = 45): Collection
    {
        $reconciliation = $line->reconciliation;

        return $reconciliation->account->transactions()
            ->where('is_reconciled', false)
            ->where('type', $line->expectedTransactionType())
            ->whereBetween('amount', [$line->absoluteAmount() - self::BALANCE_TOLERANCE, $line->absoluteAmount() + self::BALANCE_TOLERANCE])
            ->whereDate('transaction_date', '>=', $line->line_date->copy()->subDays($dayWindow)->toDateString())
            ->whereDate('transaction_date', '<=', $line->line_date->copy()->addDays($dayWindow)->toDateString())
            ->get()
            ->sortBy(fn (Transaction $t): int => abs((int) $t->transaction_date->diffInDays($line->line_date, false)))
            ->values();
    }

    // ─── INTERNE ─────────────────────────────────────────────────────────────

    private function assertDraft(BankReconciliation $reconciliation): void
    {
        if (! $reconciliation->isDraft()) {
            throw BankReconciliationException::completed();
        }
    }

    private function attach(BankReconciliation $reconciliation, BankStatementLine $line, Transaction $transaction): void
    {
        if (! $transaction->is_reconciled) {
            $transaction->reconcile($reconciliation);
        }

        $line->update([
            'status' => BankStatementLineStatus::Matched,
            'matched_transaction_id' => $transaction->id,
        ]);
    }

    private function isCompatible(BankStatementLine $line, Transaction $transaction, int $dayTolerance): bool
    {
        return $transaction->type === $line->expectedTransactionType()
            && abs((float) $transaction->amount - $line->absoluteAmount()) < self::BALANCE_TOLERANCE
            && abs($transaction->transaction_date->diffInDays($line->line_date, false)) <= $dayTolerance;
    }

    private function sameReference(BankStatementLine $line, Transaction $transaction): bool
    {
        $lineReference = mb_strtolower(trim((string) $line->reference));
        $transactionReference = mb_strtolower(trim((string) $transaction->reference_number));

        return $lineReference !== '' && $transactionReference !== '' && $lineReference === $transactionReference;
    }

    /**
     * @param  list<string>  $headers
     * @return array{date: string, label: ?string, amount: ?string, debit: ?string, credit: ?string, reference: ?string}
     */
    private function resolveColumns(array $headers): array
    {
        $resolved = [];

        foreach (self::HEADER_ALIASES as $column => $aliases) {
            foreach ($aliases as $alias) {
                if (in_array($alias, $headers, true)) {
                    $resolved[$column] = $alias;

                    break;
                }
            }
        }

        // Repli : un en-tête qui commence par « date » ou contient « libell »
        $resolved['date'] ??= collect($headers)->first(fn (string $h): bool => str_starts_with($h, 'date'));
        $resolved['label'] ??= collect($headers)->first(fn (string $h): bool => str_contains($h, 'libell'));

        $missing = [];

        if (! isset($resolved['date'])) {
            $missing[] = 'Date';
        }

        if (! isset($resolved['amount']) && ! (isset($resolved['debit']) || isset($resolved['credit']))) {
            $missing[] = 'Montant (ou Débit / Crédit)';
        }

        if ($missing !== []) {
            throw BankReconciliationException::unreadableStatement($missing);
        }

        return [
            'date' => $resolved['date'],
            'label' => $resolved['label'] ?? null,
            'amount' => $resolved['amount'] ?? null,
            'debit' => $resolved['debit'] ?? null,
            'credit' => $resolved['credit'] ?? null,
            'reference' => $resolved['reference'] ?? null,
        ];
    }

    /**
     * Montant signé d'une ligne : colonne « montant » telle quelle, sinon crédit − débit.
     *
     * @param  array<string, mixed>  $values
     * @param  array{date: string, label: ?string, amount: ?string, debit: ?string, credit: ?string, reference: ?string}  $columns
     */
    private function resolveAmount(array $values, array $columns): ?float
    {
        if ($columns['amount'] !== null) {
            $amount = self::parseAmount($values[$columns['amount']] ?? null);

            return $amount !== null && abs($amount) >= self::BALANCE_TOLERANCE ? $amount : null;
        }

        $debit = $columns['debit'] !== null ? self::parseAmount($values[$columns['debit']] ?? null) : null;
        $credit = $columns['credit'] !== null ? self::parseAmount($values[$columns['credit']] ?? null) : null;

        if ($debit === null && $credit === null) {
            return null;
        }

        $amount = round(abs($credit ?? 0) - abs($debit ?? 0), 2);

        return abs($amount) >= self::BALANCE_TOLERANCE ? $amount : null;
    }

    /**
     * Lit un montant tel qu'exporté par les banques : « 1 250 000 », « 1.250.000 », « 1 250,50 »,
     * « -5 000 », « (5 000) », « 25 000 XOF ». Un point suivi d'exactement trois chiffres est un
     * séparateur de milliers (montants en francs CFA sans décimales).
     */
    public static function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = str_replace(["\u{00A0}", "\u{202F}", ' '], '', trim((string) $value));
        $negative = str_starts_with($raw, '(') && str_ends_with($raw, ')');
        $raw = preg_replace('/[^0-9,.\-]/', '', $raw) ?? '';

        if ($raw === '' || $raw === '-') {
            return null;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            // Le dernier séparateur est la décimale, l'autre sépare les milliers
            $decimal = strrpos($raw, ',') > strrpos($raw, '.') ? ',' : '.';
            $raw = str_replace($decimal === ',' ? '.' : ',', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = preg_match('/,\d{1,2}$/', $raw) === 1 ? str_replace(',', '.', $raw) : str_replace(',', '', $raw);
        } elseif (substr_count($raw, '.') > 1 || preg_match('/\.\d{3}$/', $raw) === 1) {
            $raw = str_replace('.', '', $raw);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        $amount = (float) $raw;

        return $negative ? -abs($amount) : $amount;
    }

    /** Lit une date « 2026-09-30 », « 30/09/2026 », « 30-09-26 », « 30.09.2026 » ou un horodatage. */
    public static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $raw = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'd/m/y', 'd-m-y', 'Y/m/d', 'Y-m-d H:i:s', 'd/m/Y H:i', 'd/m/Y H:i:s'] as $format) {
            if (! Carbon::hasFormat($raw, $format)) {
                continue;
            }

            $date = Carbon::createFromFormat('!'.$format, $raw);

            if ($date instanceof Carbon && $date->format($format) === $raw) {
                return $date->startOfDay();
            }
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function lineFingerprint(CarbonInterface $date, float $amount, string $label): string
    {
        return $date->toDateString().'|'.number_format($amount, 2, '.', '').'|'.mb_strtolower(trim($label));
    }
}
