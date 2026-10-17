<?php

namespace App\Models;

use App\Enums\AccountType;
use App\Enums\MobileMoneyOperator;
use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Account extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id',
        'name', 'code', 'type',
        'bank_name', 'account_number', 'phone_number', 'operator',
        'initial_balance', 'initial_balance_date', 'current_balance', 'currency',
        'description', 'notes',
        'is_default', 'is_active',
    ];

    /**
     * Référence de la transaction d'ouverture générée à la création du compte : elle porte le solde
     * initial dans les mouvements, qui ne doit donc plus être ajouté à part dans les calculs.
     */
    public const string OPENING_REFERENCE = 'OUVERTURE';

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'operator' => MobileMoneyOperator::class,
            'initial_balance' => 'decimal:2',
            'initial_balance_date' => 'date',
            'current_balance' => 'decimal:2',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Account $account): void {
            if ($account->organisation_id === null && $account->farm_id !== null) {
                $account->organisation_id = Farm::find($account->farm_id)?->organisation_id;
            }
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

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Rapprochements par relevé ouverts ou clôturés sur ce compte. */
    public function bankReconciliations(): HasMany
    {
        return $this->hasMany(BankReconciliation::class);
    }

    public function outgoingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'from_account_id');
    }

    public function incomingTransfers(): HasMany
    {
        return $this->hasMany(AccountTransfer::class, 'to_account_id');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where('organisation_id', $organisationId);
    }

    public function scopeForFarm(Builder $query, int $farmId): Builder
    {
        return $query->where('farm_id', $farmId);
    }

    public function scopeOfType(Builder $query, AccountType|string $type): Builder
    {
        return $query->where('type', $type instanceof AccountType ? $type->value : $type);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isCash(): bool
    {
        return $this->type === AccountType::Cash;
    }

    public function isBank(): bool
    {
        return $this->type === AccountType::Bank;
    }

    public function isMobileMoney(): bool
    {
        return $this->type === AccountType::MobileMoney;
    }

    public function isDefault(): bool
    {
        return (bool) $this->is_default;
    }

    public function hasPositiveBalance(): bool
    {
        return (float) $this->current_balance > 0;
    }

    public function canDebit(float $amount): bool
    {
        return $this->is_active && (float) $this->current_balance >= $amount;
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    /**
     * Crédite le compte. Le solde est relu sous verrou (SELECT … FOR UPDATE)
     * afin que deux encaissements simultanés ne s'écrasent pas.
     *
     * @param  \DateTimeInterface|string|null  $date  Date comptable (défaut : aujourd'hui)
     */
    public function credit(
        float $amount,
        string $description,
        TransactionCategory $category = TransactionCategory::Other,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        \DateTimeInterface|string|null $date = null,
    ): Transaction {
        return $this->recordMovement(TransactionType::Credit, $amount, $description, $category, $referenceType, $referenceId, $referenceNumber, $date);
    }

    /**
     * Débite le compte, sous le même verrou que credit().
     *
     * @param  \DateTimeInterface|string|null  $date  Date comptable (défaut : aujourd'hui)
     */
    public function debit(
        float $amount,
        string $description,
        TransactionCategory $category = TransactionCategory::Other,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $referenceNumber = null,
        \DateTimeInterface|string|null $date = null,
    ): Transaction {
        return $this->recordMovement(TransactionType::Debit, $amount, $description, $category, $referenceType, $referenceId, $referenceNumber, $date);
    }

    /**
     * Écriture atomique : verrou de la ligne du compte, calcul du solde à partir
     * de la valeur en base (jamais de la valeur en mémoire), puis mise à jour.
     */
    protected function recordMovement(
        TransactionType $type,
        float $amount,
        string $description,
        TransactionCategory $category,
        ?string $referenceType,
        ?int $referenceId,
        ?string $referenceNumber,
        \DateTimeInterface|string|null $date,
    ): Transaction {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Le montant d\'une écriture doit être strictement positif.');
        }

        return DB::transaction(function () use ($type, $amount, $description, $category, $referenceType, $referenceId, $referenceNumber, $date): Transaction {
            /** @var Account $locked */
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());

            $balanceBefore = (float) $locked->current_balance;
            $balanceAfter = $type === TransactionType::Credit
                ? $balanceBefore + $amount
                : $balanceBefore - $amount;

            $transaction = $locked->transactions()->make([
                'user_id' => auth()->id(),
                'type' => $type,
                'category' => $category,
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'reference_number' => $referenceNumber,
                'description' => $description,
                'transaction_date' => $date ? Carbon::parse($date)->toDateString() : now()->toDateString(),
            ]);

            // Colonnes dérivées, hors assignation de masse
            $transaction->forceFill([
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ])->save();

            $locked->forceFill(['current_balance' => $balanceAfter])->save();

            // Synchroniser l'instance appelante avec la valeur réellement persistée
            $this->current_balance = $balanceAfter;
            $this->syncOriginalAttribute('current_balance');

            return $transaction;
        });
    }

    public function getTotalCredits(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return (float) $this->transactions()
            ->where('type', TransactionType::Credit)
            ->when($from, fn (Builder $q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('transaction_date', '<=', $to))
            ->sum('amount');
    }

    public function getTotalDebits(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return (float) $this->transactions()
            ->where('type', TransactionType::Debit)
            ->when($from, fn (Builder $q) => $q->where('transaction_date', '>=', $from))
            ->when($to, fn (Builder $q) => $q->where('transaction_date', '<=', $to))
            ->sum('amount');
    }

    public function getNetMovement(?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): float
    {
        return $this->getTotalCredits($from, $to) - $this->getTotalDebits($from, $to);
    }

    public function recalculateBalance(): void
    {
        $credits = (float) $this->transactions()->where('type', TransactionType::Credit)->sum('amount');
        $debits = (float) $this->transactions()->where('type', TransactionType::Debit)->sum('amount');
        $this->forceFill(['current_balance' => $this->standaloneInitialBalance() + $credits - $debits])->save();
    }

    /**
     * Solde initial à ajouter aux mouvements : zéro dès que la transaction d'ouverture le porte déjà
     * (comptes créés par le service), sinon la valeur de la fiche (comptes importés ou de test).
     */
    public function standaloneInitialBalance(): float
    {
        return $this->carriesOpeningTransaction() ? 0.0 : (float) $this->initial_balance;
    }

    public function carriesOpeningTransaction(): bool
    {
        return $this->transactions()->where('reference_number', self::OPENING_REFERENCE)->exists();
    }

    public function setAsDefault(): void
    {
        static::query()
            ->where('organisation_id', $this->organisation_id)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        $this->update(['is_default' => true]);
    }
}
