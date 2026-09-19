<?php

namespace App\Models;

use App\Enums\CreditNoteStatus;
use App\Enums\SaleStatus;
use App\Enums\TransactionCategory;
use App\Exceptions\CommercialException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class CreditNote extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'sale_id', 'customer_id', 'user_id',
        'credit_note_number', 'issue_date',
        'amount', 'reason', 'description',
        'status', 'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'amount' => 'decimal:2',
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
     * Impute l'avoir sur la facture : réduit le solde dû et, si un compte est lié,
     * contrepasse le montant comptable (débit du compte).
     */
    public function apply(): void
    {
        if (! $this->canBeApplied()) {
            throw CommercialException::creditNoteNotApplicable($this->credit_note_number);
        }

        DB::transaction(function (): void {
            $sale = $this->sale;
            $amount = (float) $this->amount;
            $remaining = min($amount, (float) $sale->balance);

            // Comptablement : si un compte a encaissé la facture, on le débite du montant remboursé
            if ($sale->account_id) {
                $sale->account?->debit(
                    amount: $remaining,
                    description: "Avoir {$this->credit_note_number} sur facture {$sale->invoice_number}",
                    category: TransactionCategory::Refund,
                    referenceType: self::class,
                    referenceId: $this->id,
                    referenceNumber: $this->credit_note_number,
                );
            }

            // Diminuer le solde dû de la facture
            $newBalance = max(0, (float) $sale->balance - $remaining);
            $newPaid = (float) $sale->total - $newBalance;

            $newStatus = match (true) {
                $newBalance <= 0 && (float) $sale->total > 0 => SaleStatus::Paid,
                $newPaid > 0 => SaleStatus::Partial,
                default => $sale->status,
            };

            $sale->update([
                'balance' => $newBalance,
                'paid' => $newPaid,
                'status' => $newStatus,
            ]);

            // Mettre à jour le solde client
            if ($sale->customer) {
                $sale->customer->update([
                    'balance' => $sale->customer->sales()->sum('balance'),
                ]);
            }

            $this->update([
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
        $count = static::withTrashed()
            ->where('organisation_id', $organisationId)
            ->whereYear('created_at', $year)
            ->count();

        return sprintf('AV-%d-%05d', $year, $count + 1);
    }
}
