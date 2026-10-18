<?php

namespace App\Models;

use App\Enums\BankReconciliationStatus;
use App\Exceptions\BankReconciliationException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Database\Factories\BankReconciliationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Rapprochement d'un compte de trésorerie avec un relevé (bancaire, mobile money, ou comptage de
 * caisse) : solde du relevé à une date, lignes importées et transactions pointées. Un seul
 * rapprochement en cours par compte, des relevés successifs dans l'ordre chronologique ; clôturé
 * quand le solde pointé égale celui du relevé, et ce solde est alors figé.
 *
 * Les relations vers le compte et ses transactions ignorent les portées globales du panel : l'accès
 * au rapprochement est déjà contrôlé, et un compte archivé ou dont la ferme a changé doit rester
 * entièrement visible pour que soldes et verrous portent sur tous ses mouvements.
 */
class BankReconciliation extends Model
{
    /** @use HasFactory<BankReconciliationFactory> */
    use BelongsToOrganisation, HasFactory, LogsAuditTrail;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'account';

    protected $fillable = [
        'organisation_id', 'farm_id', 'account_id',
        'statement_date', 'statement_balance', 'reconciled_balance', 'status',
        'statement_file', 'notes',
        'completed_at', 'completed_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'statement_date' => 'date',
            'statement_balance' => 'decimal:2',
            'reconciled_balance' => 'decimal:2',
            'status' => BankReconciliationStatus::class,
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Un rapprochement clôturé fait partie de la piste d'audit ; un brouillon supprimé libère ses transactions
        static::deleting(function (BankReconciliation $reconciliation): void {
            if ($reconciliation->isCompleted()) {
                throw BankReconciliationException::cannotDeleteCompleted();
            }

            DB::transaction(function () use ($reconciliation): void {
                $reconciliation->transactions()->get()->each(function (Transaction $transaction) use ($reconciliation): void {
                    $transaction->setRelation('bankReconciliation', $reconciliation)->unreconcile();
                });
            });
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    /** Compte rapproché, même archivé. */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class)->withoutGlobalScopes();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** Lignes importées du relevé. */
    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class)->chaperone('reconciliation');
    }

    /** Transactions pointées dans ce rapprochement. */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class)->withoutGlobalScopes();
    }

    /**
     * Transactions du compte à examiner dans ce rapprochement, toutes datées au plus tard du relevé :
     * non pointées, pointées ici, ou pointées hors de tout rapprochement (pointage à l'unité ou
     * antérieur au module), qui comptent dans le solde et seront rattachées à la clôture.
     */
    public function candidateTransactions(): HasMany
    {
        $statementDate = $this->statement_date?->toDateString() ?? today()->toDateString();

        return $this->hasMany(Transaction::class, 'account_id', 'account_id')
            ->withoutGlobalScopes()
            ->whereDate('transactions.transaction_date', '<=', $statementDate)
            ->where(function (Builder $query): void {
                $query
                    ->where('transactions.is_reconciled', false)
                    ->orWhereNull('transactions.bank_reconciliation_id')
                    ->orWhere('transactions.bank_reconciliation_id', $this->getKey() ?? 0);
            });
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), BankReconciliationStatus::Draft->value);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('status'), BankReconciliationStatus::Completed->value);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === BankReconciliationStatus::Draft;
    }

    public function isCompleted(): bool
    {
        return $this->status === BankReconciliationStatus::Completed;
    }

    /** « Banque — relevé au 30/09/2026 ». */
    public function label(): string
    {
        return ($this->account?->name ?? 'Compte').' — relevé au '.$this->statement_date?->format('d/m/Y');
    }
}
