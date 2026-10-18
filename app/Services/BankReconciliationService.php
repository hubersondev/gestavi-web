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
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement d'un compte avec un relevé : ouverture, import des lignes du relevé (Excel / CSV),
 * correspondance automatique montant + date (± quelques jours) + référence, pointage manuel,
 * clôture quand le solde pointé égale le solde du relevé.
 *
 * Règles :
 * - les relevés d'un compte se suivent : un seul en cours, toujours daté après le dernier clôturé ;
 * - seules les transactions datées au plus tard du relevé s'y pointent ;
 * - solde pointé à la date D = solde initial (s'il n'est pas porté par la transaction d'ouverture)
 *   + mouvements pointés dans un rapprochement du compte daté au plus tard de D
 *   + mouvements pointés hors rapprochement (pointage à l'unité) datés au plus tard de D ;
 * - à la clôture, ce solde est figé et les pointages hors rapprochement y sont rattachés ;
 * - le franc CFA n'a pas de subdivision : montants et soldes concordent à moins d'un franc près.
 *
 * Toutes les requêtes sur le compte et ses transactions ignorent les portées globales du panel :
 * l'accès au rapprochement est déjà contrôlé, et les calculs doivent porter sur tout le compte.
 */
class BankReconciliationService
{
    /** Écart toléré (strictement inférieur) entre deux montants ou deux soldes, en francs. */
    public const float TOLERANCE = 1.0;

    /** Décalage de date admis entre une ligne de relevé et la transaction correspondante. */
    public const int DEFAULT_DAY_TOLERANCE = 3;

    /** Fenêtre de dates des transactions proposées pour une ligne. */
    public const int SUGGESTION_DAY_WINDOW = 45;

    /** En-têtes reconnus (normalisés par SpreadsheetReader::normalizeHeader), par colonne attendue. */
    public const array HEADER_ALIASES = [
        'date' => ['date', 'date_operation', 'date_d_operation', 'date_op', 'date_compta', 'date_comptable', 'jour', 'date_valeur', 'date_de_valeur'],
        'label' => ['libelle', 'libelle_operation', 'libelle_de_l_operation', 'description', 'intitule', 'label', 'designation', 'operation', 'motif', 'nature'],
        'amount' => ['montant', 'amount', 'montant_xof', 'montant_fcfa', 'montant_operation'],
        'debit' => ['debit', 'retrait', 'retraits', 'sortie', 'sorties', 'montant_debit', 'debit_xof', 'depense'],
        'credit' => ['credit', 'depot', 'depots', 'entree', 'entrees', 'versement', 'montant_credit', 'credit_xof', 'recette'],
        'reference' => ['reference', 'ref', 'numero', 'n', 'no', 'piece', 'n_piece', 'numero_piece', 'id_transaction', 'transaction_id', 'identifiant'],
    ];

    public function __construct(private readonly SpreadsheetReader $reader) {}

    // ─── OUVERTURE / MODIFICATION ────────────────────────────────────────────

    /**
     * Ouvre un rapprochement sur un compte ; un seul brouillon à la fois par compte, daté après le
     * dernier rapprochement clôturé. La transaction d'ouverture du compte, point de départ commun
     * de l'application et de la banque, y est pointée d'office.
     */
    public function start(
        Account $account,
        CarbonInterface|string $statementDate,
        float $statementBalance,
        ?string $notes = null,
        ?int $userId = null,
    ): BankReconciliation {
        $date = Carbon::parse($statementDate)->startOfDay();

        if ($this->reconciliationsOf($account->id)->draft()->exists()) {
            throw BankReconciliationException::alreadyOpen($account->name);
        }

        $this->assertAfterLastCompleted($account->id, $date);

        try {
            $reconciliation = DB::transaction(fn (): BankReconciliation => BankReconciliation::query()->create([
                'organisation_id' => $account->organisation_id,
                'farm_id' => $account->farm_id,
                'account_id' => $account->id,
                'statement_date' => $date->toDateString(),
                'statement_balance' => $statementBalance,
                'status' => BankReconciliationStatus::Draft,
                'notes' => $notes,
                'created_by' => $userId ?? auth()->id(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // Double envoi du formulaire : l'index unique partiel garantit un seul brouillon
            throw BankReconciliationException::alreadyOpen($account->name);
        }

        $opening = $this->transactionsOf($reconciliation)
            ->where('reference_number', Account::OPENING_REFERENCE)
            ->where('is_reconciled', false)
            ->whereDate('transaction_date', '<=', $date->toDateString())
            ->first();

        $opening?->reconcile($reconciliation);

        return $reconciliation;
    }

    /**
     * Modifie la date, le solde ou les notes d'un brouillon : la date reste postérieure au dernier
     * rapprochement clôturé et ne peut pas passer avant une transaction déjà pointée.
     */
    public function update(BankReconciliation $reconciliation, CarbonInterface|string $statementDate, float $statementBalance, ?string $notes): BankReconciliation
    {
        $this->assertDraft($reconciliation);

        $date = Carbon::parse($statementDate)->startOfDay();
        $this->assertAfterLastCompleted($reconciliation->account_id, $date, exceptId: $reconciliation->id);

        $pointedAfter = $this->transactionsOf($reconciliation)
            ->where('bank_reconciliation_id', $reconciliation->id)
            ->whereDate('transaction_date', '>', $date->toDateString())
            ->count();

        if ($pointedAfter > 0) {
            throw BankReconciliationException::pointedAfterDate($pointedAfter, $date->format('d/m/Y'));
        }

        $reconciliation->update([
            'statement_date' => $date->toDateString(),
            'statement_balance' => $statementBalance,
            'notes' => $notes,
        ]);

        return $reconciliation;
    }

    // ─── SYNTHÈSE ────────────────────────────────────────────────────────────

    /**
     * Solde pointé à la date du relevé (figé une fois le rapprochement clôturé).
     */
    public function reconciledBalance(BankReconciliation $reconciliation): float
    {
        if ($reconciliation->isCompleted() && $reconciliation->reconciled_balance !== null) {
            return (float) $reconciliation->reconciled_balance;
        }

        return $this->computeReconciledBalance($reconciliation);
    }

    /**
     * @return array{
     *     statement_balance: float,
     *     reconciled_balance: float,
     *     difference: float,
     *     is_balanced: bool,
     *     pending_count: int,
     *     pending_amount: float,
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

        $pendingCount = 0;
        $pendingAmount = 0.0;

        if ($reconciliation->isDraft()) {
            $pending = $this->transactionsOf($reconciliation)
                ->where('is_reconciled', false)
                ->whereDate('transaction_date', '<=', $reconciliation->statement_date->toDateString())
                ->toBase()
                ->selectRaw('COUNT(*) AS pending_count')
                ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS pending_amount', [TransactionType::Credit->value])
                ->first();

            $pendingCount = (int) ($pending->pending_count ?? 0);
            $pendingAmount = round((float) ($pending->pending_amount ?? 0), 2);
        }

        $lines = $reconciliation->lines()
            ->toBase()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'statement_balance' => $statement,
            'reconciled_balance' => $reconciled,
            'difference' => $difference,
            'is_balanced' => self::isWithinTolerance($difference),
            'pending_count' => $pendingCount,
            'pending_amount' => $pendingAmount,
            'lines_total' => (int) $lines->sum(),
            'lines_matched' => (int) ($lines[BankStatementLineStatus::Matched->value] ?? 0),
            'lines_unmatched' => (int) ($lines[BankStatementLineStatus::Unmatched->value] ?? 0),
            'lines_ignored' => (int) ($lines[BankStatementLineStatus::Ignored->value] ?? 0),
        ];
    }

    // ─── POINTAGE ────────────────────────────────────────────────────────────

    /**
     * Pointe (ou dépointe) des transactions du compte dans ce rapprochement. Une transaction pointée
     * hors rapprochement y est rattachée (ou dépointée) ; une transaction pointée dans un autre
     * rapprochement est refusée.
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
            $transactions = $this->transactionsOf($reconciliation)->whereKey($ids)->get()->keyBy('id');

            if ($foreign = $ids->first(fn (int $id): bool => ! $transactions->has($id))) {
                throw BankReconciliationException::foreignTransaction($foreign);
            }

            if (! $reconciled) {
                // Les lignes du relevé associées à ces transactions redeviennent à rapprocher
                $reconciliation->lines()
                    ->whereIn('matched_transaction_id', $ids)
                    ->update(['matched_transaction_id' => null, 'status' => BankStatementLineStatus::Unmatched->value]);
            }

            $count = 0;

            foreach ($transactions as $transaction) {
                $linkedElsewhere = $transaction->bank_reconciliation_id !== null
                    && (int) $transaction->bank_reconciliation_id !== (int) $reconciliation->id;

                if ($reconciled) {
                    $this->assertNotAfterStatement($reconciliation, $transaction);

                    if ($transaction->is_reconciled && $linkedElsewhere) {
                        throw BankReconciliationException::transactionAlreadyReconciled($transaction->id);
                    }

                    if ($transaction->is_reconciled && $transaction->bank_reconciliation_id !== null) {
                        continue;
                    }

                    $transaction->reconcile($reconciliation);
                    $count++;

                    continue;
                }

                if (! $transaction->is_reconciled) {
                    continue;
                }

                if ($linkedElsewhere) {
                    throw BankReconciliationException::transactionAlreadyReconciled($transaction->id);
                }

                $transaction->setRelation('bankReconciliation', $reconciliation)->unreconcile();
                $count++;
            }

            return $count;
        });
    }

    // ─── RELEVÉ IMPORTÉ ──────────────────────────────────────────────────────

    /**
     * Importe les lignes d'un relevé (xlsx / csv). La ligne d'en-tête est cherchée sous un éventuel
     * préambule ; colonnes reconnues : date, libellé, débit / crédit ou montant signé, référence.
     * Les lignes postérieures à la date du relevé sont écartées. Une ligne déjà importée (même date,
     * montant, libellé et référence) ne l'est pas deux fois, mais deux lignes identiques d'un même
     * relevé (deux retraits identiques le même jour) sont bien deux mouvements.
     *
     * @return array{imported: int, duplicates: int, after_statement: int, errors: list<string>}
     */
    public function importStatement(BankReconciliation $reconciliation, string $path, ?string $fileName = null): array
    {
        $this->assertDraft($reconciliation);

        $sheet = $this->reader->read($path, fn (array $headers): bool => $this->resolveColumns($headers) !== null);
        $columns = $this->resolveColumns($sheet['headers']);

        if ($columns === null) {
            throw BankReconciliationException::unreadableStatement(['Date', 'Montant (ou Débit / Crédit)']);
        }

        $statementDate = $reconciliation->statement_date->toDateString();

        // Empreintes déjà présentes, comptées : un relevé réimporté n'ajoute que les lignes en plus
        $existing = $reconciliation->lines()->toBase()->get(['line_date', 'amount', 'label', 'reference'])
            ->countBy(fn (object $line): string => $this->lineFingerprint(
                substr((string) $line->line_date, 0, 10),
                (float) $line->amount,
                (string) $line->label,
                $line->reference,
            ))
            ->all();
        $seen = [];

        $imported = 0;
        $duplicates = 0;
        $afterStatement = 0;
        $errors = [];
        $rows = [];

        foreach ($sheet['rows'] as ['line' => $lineNumber, 'values' => $values]) {
            $date = static::parseDate($values[$columns['date']] ?? null);

            if ($date === null) {
                $errors[] = "Ligne {$lineNumber} : date illisible.";

                continue;
            }

            if ($date->toDateString() > $statementDate) {
                $afterStatement++;

                continue;
            }

            $amount = $this->resolveAmount($values, $columns);

            if ($amount === null) {
                $errors[] = "Ligne {$lineNumber} : montant illisible ou nul.";

                continue;
            }

            $label = mb_substr(trim((string) ($columns['label'] !== null ? ($values[$columns['label']] ?? '') : '')) ?: 'Opération du '.$date->format('d/m/Y'), 0, 255);
            $reference = $columns['reference'] !== null ? (mb_substr(trim((string) ($values[$columns['reference']] ?? '')), 0, 100) ?: null) : null;
            $fingerprint = $this->lineFingerprint($date->toDateString(), $amount, $label, $reference);

            $seen[$fingerprint] = ($seen[$fingerprint] ?? 0) + 1;

            if ($seen[$fingerprint] <= ($existing[$fingerprint] ?? 0)) {
                $duplicates++;

                continue;
            }

            $rows[] = [
                'bank_reconciliation_id' => $reconciliation->id,
                'line_date' => $date->toDateString(),
                'label' => $label,
                'amount' => $amount,
                'reference' => $reference,
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

        return ['imported' => $imported, 'duplicates' => $duplicates, 'after_statement' => $afterStatement, 'errors' => $errors];
    }

    /**
     * Associe automatiquement les lignes à rapprocher à des transactions non pointées de même sens,
     * de même montant (à moins d'un franc) et datées à ± $dayTolerance jours, jamais postérieures au
     * relevé. Les lignes dont la référence se retrouve sur une transaction passent d'abord ; ensuite
     * la date la plus proche départage. Chaque transaction n'est prise qu'une fois.
     *
     * @return int Nombre de lignes rapprochées
     */
    public function autoMatch(BankReconciliation $reconciliation, int $dayTolerance = self::DEFAULT_DAY_TOLERANCE): int
    {
        $this->assertDraft($reconciliation);

        $lines = $reconciliation->lines()->where('status', BankStatementLineStatus::Unmatched->value)->orderBy('line_date')->orderBy('id')->get();

        if ($lines->isEmpty()) {
            return 0;
        }

        $from = $lines->min('line_date')->copy()->subDays($dayTolerance)->toDateString();
        $to = min($lines->max('line_date')->copy()->addDays($dayTolerance)->toDateString(), $reconciliation->statement_date->toDateString());

        // Candidats rangés par sens et par franc arrondi : chaque ligne ne regarde que trois paniers
        $buckets = $this->transactionsOf($reconciliation)
            ->where('is_reconciled', false)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->get()
            ->groupBy(fn (Transaction $t): string => $t->type->value.'|'.(int) round((float) $t->amount));

        $used = [];
        $pairs = [];

        $pick = function (BankStatementLine $line, bool $sameReferenceOnly) use ($buckets, $dayTolerance, &$used): ?Transaction {
            $type = $line->expectedTransactionType()->value;
            $key = (int) round($line->absoluteAmount());

            return collect([$key - 1, $key, $key + 1])
                ->flatMap(fn (int $k): Collection => $buckets->get($type.'|'.$k, collect()))
                ->reject(fn (Transaction $t): bool => isset($used[$t->id]))
                ->filter(fn (Transaction $t): bool => $this->isCompatible($line, $t, $dayTolerance)
                    && (! $sameReferenceOnly || $this->sameReference($line, $t)))
                ->sortBy(fn (Transaction $t): array => [abs($t->transaction_date->diffInDays($line->line_date, false)), $t->id])
                ->first();
        };

        foreach ([true, false] as $sameReferenceOnly) {
            foreach ($lines as $line) {
                if (isset($pairs[$line->id])) {
                    continue;
                }

                $transaction = $pick($line, $sameReferenceOnly);

                if ($transaction instanceof Transaction) {
                    $used[$transaction->id] = true;
                    $pairs[$line->id] = [$line, $transaction];
                }
            }
        }

        DB::transaction(function () use ($reconciliation, $pairs): void {
            foreach ($pairs as [$line, $transaction]) {
                $this->attach($reconciliation, $line, $transaction);
            }
        });

        return count($pairs);
    }

    /**
     * Associe manuellement une ligne du relevé à une transaction du compte, qui est pointée. Une
     * transaction déjà pointée ici (ou hors rapprochement) peut être associée tant qu'aucune autre
     * ligne ne la porte.
     */
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

        $this->assertNotAfterStatement($reconciliation, $transaction);

        if ($transaction->type !== $line->expectedTransactionType() || ! self::amountsMatch((float) $transaction->amount, $line->absoluteAmount())) {
            throw BankReconciliationException::amountMismatch((float) $line->amount, (float) $transaction->amount);
        }

        if ($transaction->is_reconciled && $transaction->bank_reconciliation_id !== null && (int) $transaction->bank_reconciliation_id !== (int) $reconciliation->id) {
            throw BankReconciliationException::transactionAlreadyReconciled($transaction->id);
        }

        if ($reconciliation->lines()->where('matched_transaction_id', $transaction->id)->exists()) {
            throw BankReconciliationException::transactionAlreadyMatched($transaction->id);
        }

        DB::transaction(fn () => $this->attach($reconciliation, $line, $transaction));
    }

    /** Dissocie une ligne : la transaction redevient à pointer. */
    public function unmatchLine(BankStatementLine $line): void
    {
        $reconciliation = $line->reconciliation;
        $this->assertDraft($reconciliation);

        DB::transaction(function () use ($line, $reconciliation): void {
            $transaction = $line->matchedTransaction;

            $line->update(['status' => BankStatementLineStatus::Unmatched, 'matched_transaction_id' => null]);

            if ($transaction instanceof Transaction && $transaction->is_reconciled) {
                $transaction->setRelation('bankReconciliation', $reconciliation)->unreconcile();
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
     * Clôture le rapprochement : refusé tant que le solde pointé diffère du solde du relevé d'un
     * franc ou plus. Les pointages hors rapprochement comptés sont rattachés à celui-ci (et donc
     * verrouillés), puis le solde pointé est figé.
     */
    public function complete(BankReconciliation $reconciliation, ?int $userId = null): BankReconciliation
    {
        $this->assertDraft($reconciliation);

        return DB::transaction(function () use ($reconciliation, $userId): BankReconciliation {
            $reconciled = $this->computeReconciledBalance($reconciliation);
            $difference = round((float) $reconciliation->statement_balance - $reconciled, 2);

            if (! self::isWithinTolerance($difference)) {
                throw BankReconciliationException::unbalanced($difference);
            }

            $this->transactionsOf($reconciliation)
                ->where('is_reconciled', true)
                ->whereNull('bank_reconciliation_id')
                ->whereDate('transaction_date', '<=', $reconciliation->statement_date->toDateString())
                ->get()
                ->each(fn (Transaction $transaction) => $transaction->reconcile($reconciliation));

            $reconciliation->update([
                'status' => BankReconciliationStatus::Completed,
                'reconciled_balance' => $reconciled,
                'completed_at' => now(),
                'completed_by' => $userId ?? auth()->id(),
            ]);

            return $reconciliation->refresh();
        });
    }

    /**
     * Transactions proposables pour une ligne : de même sens et de même montant (à moins d'un franc),
     * datées au plus tard du relevé, non pointées ou pointées sans être portées par une autre ligne ;
     * triées par proximité de date.
     *
     * @return Collection<int, Transaction>
     */
    public function suggestionsFor(BankStatementLine $line): Collection
    {
        $reconciliation = $line->reconciliation;
        $amount = $line->absoluteAmount();
        $matchedIds = $reconciliation->lines()->whereNotNull('matched_transaction_id')->pluck('matched_transaction_id');

        return $this->transactionsOf($reconciliation)
            ->where('type', $line->expectedTransactionType())
            ->where('amount', '>', $amount - self::TOLERANCE)
            ->where('amount', '<', $amount + self::TOLERANCE)
            ->whereDate('transaction_date', '>=', $line->line_date->copy()->subDays(self::SUGGESTION_DAY_WINDOW)->toDateString())
            ->whereDate('transaction_date', '<=', min($line->line_date->copy()->addDays(self::SUGGESTION_DAY_WINDOW)->toDateString(), $reconciliation->statement_date->toDateString()))
            ->whereNotIn('id', $matchedIds)
            ->where(fn (Builder $query): Builder => $query
                ->where('is_reconciled', false)
                ->orWhereNull('bank_reconciliation_id')
                ->orWhere('bank_reconciliation_id', $reconciliation->id))
            ->get()
            ->sortBy(fn (Transaction $t): array => [abs($t->transaction_date->diffInDays($line->line_date, false)), $t->id])
            ->values();
    }

    // ─── FORMATS ─────────────────────────────────────────────────────────────

    /** « 1 250 000 XOF », ou « 14 567,10 XOF » quand il y a des centimes. */
    public static function money(float $amount): string
    {
        $decimals = abs($amount - round($amount)) >= 0.005 ? 2 : 0;

        return number_format($amount, $decimals, ',', ' ').' XOF';
    }

    public static function amountsMatch(float $first, float $second): bool
    {
        return self::isWithinTolerance($first - $second);
    }

    public static function isWithinTolerance(float $difference): bool
    {
        return abs(round($difference, 2)) < self::TOLERANCE;
    }

    /**
     * Lit un montant tel qu'exporté par les banques : « 1 250 000 », « 1.250.000 », « 1 250,50 »,
     * « -5 000 », « −5 000 », « (5 000) XOF », « 5 000- », « 5 000 DB » / « CR ». Un point suivi
     * d'exactement trois chiffres est un séparateur de milliers (francs CFA sans décimales).
     */
    public static function parseAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $raw = str_replace(["\u{00A0}", "\u{202F}", "\u{2009}", ' '], '', trim((string) $value));
        $raw = str_replace(["\u{2212}", "\u{2013}", "\u{2014}"], '-', $raw);

        $negative = false;

        if (preg_match('/(DB|DR|D)$/i', $raw) === 1 && preg_match('/\d/', $raw) === 1) {
            $negative = true;
            $raw = (string) preg_replace('/(DB|DR|D)$/i', '', $raw);
        } elseif (preg_match('/(CR|C)$/i', $raw) === 1) {
            $raw = (string) preg_replace('/(CR|C)$/i', '', $raw);
        }

        // Unité monétaire et texte retirés avant de lire le signe
        $raw = (string) preg_replace('/[^0-9,.\-()]/', '', $raw);

        if (str_contains($raw, '(') && str_contains($raw, ')')) {
            $negative = true;
        }

        if (str_starts_with($raw, '-') || str_ends_with($raw, '-')) {
            $negative = true;
        }

        $raw = str_replace(['(', ')', '-'], '', $raw);

        if ($raw === '') {
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

        $amount = abs((float) $raw);

        return $negative ? -$amount : $amount;
    }

    /**
     * Lit une date de relevé : « 2026-09-30 », « 30/09/2026 », « 5/9/2026 », « 30-09-26 »,
     * « 30.09.2026 », horodatage ISO. Une date impossible (31/02) ou un texte quelconque est
     * refusé plutôt que deviné.
     */
    public static function parseDate(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $raw = trim((string) $value);

        if (preg_match('/^(\d{4}-\d{2}-\d{2})[T ]\d{2}:\d{2}/', $raw, $matches) === 1) {
            $raw = $matches[1];
        } elseif (preg_match('#^(\d{1,2}[/.-]\d{1,2}[/.-]\d{2,4}) \d{1,2}:\d{2}(:\d{2})?$#', $raw, $matches) === 1) {
            $raw = $matches[1];
        }

        foreach (['Y-m-d', 'Y/m/d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd.m.Y', 'j.n.Y', 'd/m/y', 'j/n/y', 'd-m-y', 'd.m.y'] as $format) {
            if (! Carbon::hasFormat($raw, $format)) {
                continue;
            }

            $date = Carbon::createFromFormat('!'.$format, $raw);

            // L'aller-retour écarte les dates débordantes (31/02 → 03/03)
            if ($date instanceof Carbon && $date->format($format) === $raw) {
                return $date->startOfDay();
            }
        }

        return null;
    }

    // ─── INTERNE ─────────────────────────────────────────────────────────────

    /** Transactions du compte rapproché, hors portées globales du panel. */
    private function transactionsOf(BankReconciliation $reconciliation): Builder
    {
        return Transaction::query()->withoutGlobalScopes()->where('account_id', $reconciliation->account_id);
    }

    private function reconciliationsOf(int $accountId): Builder
    {
        return BankReconciliation::query()->withoutGlobalScopes()->where('account_id', $accountId);
    }

    private function computeReconciledBalance(BankReconciliation $reconciliation): float
    {
        $date = $reconciliation->statement_date->toDateString();
        $upToDate = $this->reconciliationsOf($reconciliation->account_id)
            ->whereDate('statement_date', '<=', $date)
            ->pluck('id')
            ->push($reconciliation->id)
            ->unique();

        $signed = (float) $this->transactionsOf($reconciliation)
            ->where('is_reconciled', true)
            ->where(fn (Builder $query): Builder => $query
                ->whereIn('bank_reconciliation_id', $upToDate)
                ->orWhere(fn (Builder $unlinked): Builder => $unlinked
                    ->whereNull('bank_reconciliation_id')
                    ->whereDate('transaction_date', '<=', $date)))
            ->toBase()
            ->selectRaw('COALESCE(SUM(CASE WHEN type = ? THEN amount ELSE -amount END), 0) AS signed_total', [TransactionType::Credit->value])
            ->value('signed_total');

        $carriesOpening = $this->transactionsOf($reconciliation)->where('reference_number', Account::OPENING_REFERENCE)->exists();
        $initial = $carriesOpening ? 0.0 : (float) Account::query()->withoutGlobalScopes()->whereKey($reconciliation->account_id)->value('initial_balance');

        return round($initial + $signed, 2);
    }

    private function assertDraft(BankReconciliation $reconciliation): void
    {
        if (! $reconciliation->isDraft()) {
            throw BankReconciliationException::completed();
        }
    }

    private function assertAfterLastCompleted(int $accountId, CarbonInterface $date, ?int $exceptId = null): void
    {
        $last = $this->reconciliationsOf($accountId)
            ->completed()
            ->when($exceptId, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
            ->max('statement_date');

        if ($last !== null && $date->toDateString() <= Carbon::parse($last)->toDateString()) {
            throw BankReconciliationException::notAfterLastCompleted(Carbon::parse($last)->format('d/m/Y'));
        }
    }

    private function assertNotAfterStatement(BankReconciliation $reconciliation, Transaction $transaction): void
    {
        if ($transaction->transaction_date->toDateString() > $reconciliation->statement_date->toDateString()) {
            throw BankReconciliationException::afterStatement($transaction->id, $reconciliation->statement_date->format('d/m/Y'));
        }
    }

    private function attach(BankReconciliation $reconciliation, BankStatementLine $line, Transaction $transaction): void
    {
        if (! $transaction->is_reconciled || $transaction->bank_reconciliation_id === null) {
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
            && self::amountsMatch((float) $transaction->amount, $line->absoluteAmount())
            && abs($transaction->transaction_date->diffInDays($line->line_date, false)) <= $dayTolerance;
    }

    private function sameReference(BankStatementLine $line, Transaction $transaction): bool
    {
        $lineReference = mb_strtolower(trim((string) $line->reference));
        $transactionReference = mb_strtolower(trim((string) $transaction->reference_number));

        return $lineReference !== '' && $transactionReference !== '' && $lineReference === $transactionReference;
    }

    /**
     * Colonnes du relevé, ou null si l'en-tête n'en est pas un (préambule) : une date et soit un
     * montant signé, soit une colonne débit ou crédit. Débit / crédit priment sur un montant.
     *
     * @param  list<string>  $headers
     * @return array{date: string, label: ?string, amount: ?string, debit: ?string, credit: ?string, reference: ?string}|null
     */
    private function resolveColumns(array $headers): ?array
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

        $hasDebitCredit = isset($resolved['debit']) || isset($resolved['credit']);

        if (! isset($resolved['date']) || (! isset($resolved['amount']) && ! $hasDebitCredit)) {
            return null;
        }

        return [
            'date' => $resolved['date'],
            'label' => $resolved['label'] ?? null,
            'amount' => $hasDebitCredit ? null : $resolved['amount'],
            'debit' => $resolved['debit'] ?? null,
            'credit' => $resolved['credit'] ?? null,
            'reference' => $resolved['reference'] ?? null,
        ];
    }

    /**
     * Montant signé d'une ligne : crédit − débit, sinon la colonne « montant » telle quelle.
     *
     * @param  array<string, mixed>  $values
     * @param  array{date: string, label: ?string, amount: ?string, debit: ?string, credit: ?string, reference: ?string}  $columns
     */
    private function resolveAmount(array $values, array $columns): ?float
    {
        if ($columns['amount'] !== null) {
            $amount = static::parseAmount($values[$columns['amount']] ?? null);

            return $amount !== null && round($amount, 2) != 0.0 ? round($amount, 2) : null;
        }

        $debit = $columns['debit'] !== null ? static::parseAmount($values[$columns['debit']] ?? null) : null;
        $credit = $columns['credit'] !== null ? static::parseAmount($values[$columns['credit']] ?? null) : null;

        if ($debit === null && $credit === null) {
            return null;
        }

        $amount = round(abs($credit ?? 0) - abs($debit ?? 0), 2);

        return $amount != 0.0 ? $amount : null;
    }

    private function lineFingerprint(string $date, float $amount, string $label, ?string $reference): string
    {
        return $date.'|'.number_format($amount, 2, '.', '').'|'.mb_strtolower(trim(mb_substr($label, 0, 255))).'|'.mb_strtolower(trim((string) $reference));
    }
}
