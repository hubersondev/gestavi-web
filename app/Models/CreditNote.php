<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use App\Enums\SaleStatus;
use App\Enums\TransactionCategory;
use App\Exceptions\CommercialException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail, SoftDeletes;

    /** Parent métier dont héritent organisation_id et farm_id. */
    protected static string $organisationParentRelation = 'sale';

    protected $fillable = [
        'farm_id',
        'organisation_id', 'sale_id', 'customer_id', 'user_id',
        'credit_note_number', 'issue_date',
        'amount', 'applied_amount', 'refunded_amount', 'refund_account_id',
        'reason', 'description',
        'status', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'amount' => 'decimal:2',
            'applied_amount' => 'decimal:2',
            'refunded_amount' => 'decimal:2',
            'status' => CreditNoteStatus::class,
            'applied_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CreditNote $creditNote): void {
            if ($creditNote->sale_id && $creditNote->organisation_id === null) {
                $sale = Sale::find($creditNote->sale_id);
                $creditNote->organisation_id = $sale?->organisation_id;
                $creditNote->customer_id ??= $sale?->customer_id;
            }

            if (empty($creditNote->credit_note_number) && $creditNote->organisation_id) {
                $creditNote->credit_note_number = self::generateNumber($creditNote->organisation_id);
            }
        });
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function refundAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'refund_account_id');
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', CreditNoteStatus::Draft);
    }

    public function scopeIssued(Builder $query): Builder
    {
        return $query->where('status', CreditNoteStatus::Issued);
    }

    public function scopeApplied(Builder $query): Builder
    {
        return $query->where('status', CreditNoteStatus::Applied);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === CreditNoteStatus::Draft;
    }

    public function isIssued(): bool
    {
        return $this->status === CreditNoteStatus::Issued;
    }

    public function isApplied(): bool
    {
        return $this->status === CreditNoteStatus::Applied;
    }

    public function isCancelled(): bool
    {
        return $this->status === CreditNoteStatus::Cancelled;
    }

    public function canBeApplied(): bool
    {
        return $this->isIssued() && $this->sale && ! $this->sale->isCancelled();
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    /**
     * Part de l'avoir qui dépasserait le solde dû de la facture, donc à rembourser réellement.
     */
    public function getRefundableAmount(): float
    {
        if (! $this->sale) {
            return 0.0;
        }

        return round(max(0, (float) $this->amount - (float) $this->sale->balance), 2);
    }

    /**
     * Impute l'avoir sur la facture en deux parts distinctes :
     *  (a) réduction de créance : diminue le solde dû, sans aucun mouvement de trésorerie
     *      (l'argent n'a jamais été encaissé) ;
     *  (b) remboursement : la part au-delà du solde dû (facture déjà réglée) est réellement
     *      rendue au client par un débit du compte de trésorerie.
     *
     * @param  int|null  $refundAccountId  Compte à débiter pour la part remboursée (défaut : compte de la facture)
     */
    public function apply(?int $refundAccountId = null): void
    {
        if (! $this->canBeApplied()) {
            throw CommercialException::creditNoteNotApplicable($this->credit_note_number);
        }

        DB::transaction(function () use ($refundAccountId): void {
            /** @var Sale $sale */
            $sale = Sale::query()->lockForUpdate()->findOrFail($this->sale_id);

            $amount = (float) $this->amount;
            $creditPart = round(min($amount, (float) $sale->balance), 2);
            $refundPart = round($amount - $creditPart, 2);

            $accountId = $refundPart > 0 ? ($refundAccountId ?? $sale->account_id) : null;

            if ($refundPart > 0 && ! $accountId) {
                throw CommercialException::refundAccountRequired($this->credit_note_number, $refundPart);
            }

            // (b) Remboursement réel de la part déjà encaissée
            if ($refundPart > 0) {
                /** @var Account $account */
                $account = Account::query()->findOrFail($accountId);

                $account->debit(
                    amount: $refundPart,
                    description: "Remboursement avoir {$this->credit_note_number} sur facture {$sale->invoice_number}",
                    category: TransactionCategory::Refund,
                    referenceType: self::class,
                    referenceId: $this->id,
                    referenceNumber: $this->credit_note_number,
                    date: $this->issue_date,
                );
            }

            // (a) Réduction de créance : le montant payé ne change pas, seul le solde dû diminue
            if ($creditPart > 0) {
                $newBalance = max(0, (float) $sale->balance - $creditPart);

                $newStatus = match (true) {
                    $sale->isCancelled() => $sale->status,
                    $newBalance <= 0 && (float) $sale->total > 0 => SaleStatus::Paid,
                    (float) $sale->paid > 0 => SaleStatus::Partial,
                    default => $sale->status,
                };

                $sale->update([
                    'balance' => $newBalance,
                    'status' => $newStatus,
                ]);
            }

            $sale->customer?->recalculateBalance();

            $this->update([
                'applied_amount' => $creditPart,
                'refunded_amount' => $refundPart,
                'refund_account_id' => $refundPart > 0 ? $accountId : null,
                'status' => CreditNoteStatus::Applied,
                'applied_at' => now(),
            ]);
        });
    }

    public function cancel(): void
    {
        if ($this->isApplied()) {
            throw CommercialException::creditNoteAlreadyApplied($this->credit_note_number);
        }

        $this->update(['status' => CreditNoteStatus::Cancelled]);
    }

    public static function generateNumber(int $organisationId): string
    {
        $year = now()->year;
        $sequence = app(\App\Services\NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'AV-',
            key: 'credit_note',
            scopeId: $organisationId,
            year: $year,
            seedUsing: fn (): int => $sequence->maxSuffix('credit_notes', 'credit_note_number', "AV-{$year}-", ['organisation_id' => $organisationId]),
        );
    }
}
