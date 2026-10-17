<?php

namespace App\Models;

use App\Enums\AccountTransferStatus;
use App\Enums\TransactionCategory;
use App\Exceptions\AccountException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use App\Services\NumberSequence;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AccountTransfer extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail;

    protected $fillable = [
        'organisation_id',
        'from_account_id', 'to_account_id',
        'user_id',
        'reference', 'amount', 'fees',
        'transfer_date', 'status',
        'description', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => AccountTransferStatus::class,
            'amount' => 'decimal:2',
            'fees' => 'decimal:2',
            'transfer_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AccountTransfer $transfer): void {
            if (empty($transfer->reference) && $transfer->organisation_id) {
                $transfer->reference = self::generateReference($transfer->organisation_id, $transfer->transfer_date ? Carbon::parse($transfer->transfer_date)->year : null);
            }
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'from_account_id');
    }

    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'to_account_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AccountTransferStatus::Pending);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', AccountTransferStatus::Completed);
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', AccountTransferStatus::Cancelled);
    }

    public function scopeForDateRange(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->whereBetween('transfer_date', [$start, $end]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === AccountTransferStatus::Pending;
    }

    public function isCompleted(): bool
    {
        return $this->status === AccountTransferStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === AccountTransferStatus::Cancelled;
    }

    public function getTotalAmount(): float
    {
        return (float) $this->amount + (float) $this->fees;
    }

    public function canBeCompleted(): bool
    {
        return $this->isPending()
            && $this->fromAccount
            && $this->fromAccount->canDebit($this->getTotalAmount());
    }

    public function canBeCancelled(): bool
    {
        return $this->isPending();
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function complete(): void
    {
        if (! $this->isPending()) {
            throw AccountException::transferAlreadyProcessed($this->reference ?? (string) $this->id);
        }

        // Les verrous doivent vivre dans la transaction, sinon ils sont relâchés immédiatement (autocommit)
        DB::transaction(function (): void {
            $fromAccount = $this->fromAccount()->lockForUpdate()->first();
            $toAccount = $this->toAccount()->lockForUpdate()->first();

            if (! $fromAccount?->is_active) {
                throw AccountException::accountNotActive($fromAccount?->name ?? 'source');
            }

            if (! $toAccount?->is_active) {
                throw AccountException::accountNotActive($toAccount?->name ?? 'destination');
            }

            $total = $this->getTotalAmount();

            if ((float) $fromAccount->current_balance < $total) {
                throw AccountException::insufficientBalance($fromAccount->name, $total, (float) $fromAccount->current_balance);
            }

            $feesPart = (float) $this->fees > 0 ? ' (frais inclus '.number_format((float) $this->fees, 0, ',', ' ').' XOF)' : '';

            $fromAccount->debit(
                amount: $total,
                description: "Transfert vers {$toAccount->name}{$feesPart}",
                category: TransactionCategory::TransferOut,
                referenceType: self::class,
                referenceId: $this->id,
                referenceNumber: $this->reference,
                date: $this->transfer_date,
            );

            $toAccount->credit(
                amount: (float) $this->amount,
                description: "Transfert depuis {$fromAccount->name}",
                category: TransactionCategory::TransferIn,
                referenceType: self::class,
                referenceId: $this->id,
                referenceNumber: $this->reference,
                date: $this->transfer_date,
            );

            $this->update(['status' => AccountTransferStatus::Completed]);
        });
    }

    public function cancel(): void
    {
        if (! $this->isPending()) {
            throw AccountException::transferCannotBeCancelled($this->reference ?? (string) $this->id);
        }

        $this->update(['status' => AccountTransferStatus::Cancelled]);
    }

    public function reverse(): void
    {
        if (! $this->isCompleted()) {
            throw AccountException::transferNotCompleted($this->reference ?? (string) $this->id);
        }

        DB::transaction(function (): void {
            $fromAccount = $this->fromAccount()->lockForUpdate()->first();
            $toAccount = $this->toAccount()->lockForUpdate()->first();

            if (! $toAccount || (float) $toAccount->current_balance < (float) $this->amount) {
                throw AccountException::insufficientBalance(
                    $toAccount?->name ?? 'destination',
                    (float) $this->amount,
                    (float) ($toAccount?->current_balance ?? 0),
                );
            }

            $toAccount->debit(
                amount: (float) $this->amount,
                description: "Contrepassation transfert {$this->reference}",
                category: TransactionCategory::Adjustment,
                referenceType: self::class,
                referenceId: $this->id,
                referenceNumber: $this->reference,
            );

            $fromAccount->credit(
                amount: $this->getTotalAmount(),
                description: "Contrepassation transfert {$this->reference}",
                category: TransactionCategory::Adjustment,
                referenceType: self::class,
                referenceId: $this->id,
                referenceNumber: $this->reference,
            );

            $this->update(['status' => AccountTransferStatus::Cancelled]);
        });
    }

    public static function generateReference(int $organisationId, ?int $year = null): string
    {
        // Numérotation par année du document, pas de la saisie : une pièce de 2025 saisie en 2026 reste en 2025
        $year ??= now()->year;
        $sequence = app(NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'TRF-',
            key: 'account_transfer',
            scopeId: $organisationId,
            year: $year,
            seedUsing: fn (): int => $sequence->maxSuffix('account_transfers', 'reference', "TRF-{$year}-", ['organisation_id' => $organisationId]),
        );
    }
}
