<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\TransactionCategory;
use App\Exceptions\CommercialException;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\CalculatesDocumentTotals;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use BelongsToOrganisation, CalculatesDocumentTotals, HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id',
        'customer_id', 'user_id', 'quote_id', 'account_id',
        'invoice_number', 'sale_date', 'due_date',
        'subtotal', 'discount_percent', 'discount_amount',
        'tax_percent', 'tax_amount', 'total', 'paid', 'balance',
        'status', 'payment_method',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'sale_date' => 'date',
            'due_date' => 'date',
            'status' => SaleStatus::class,
            'payment_method' => PaymentMethod::class,
            'subtotal' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'paid' => 'decimal:2',
            'balance' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Sale $sale): void {
            if ($sale->organisation_id === null && $sale->farm_id !== null) {
                $sale->organisation_id = Farm::find($sale->farm_id)?->organisation_id;
            }

            if (empty($sale->invoice_number) && $sale->organisation_id) {
                $sale->invoice_number = self::generateInvoiceNumber($sale->organisation_id);
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creditNotes(): HasMany
    {
        return $this->hasMany(CreditNote::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Pending);
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('status', SaleStatus::Paid);
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereIn('status', [SaleStatus::Pending, SaleStatus::Partial]);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [SaleStatus::Pending, SaleStatus::Partial])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeForDateRange(Builder $query, \DateTimeInterface $start, \DateTimeInterface $end): Builder
    {
        return $query->whereBetween('sale_date', [$start, $end]);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === SaleStatus::Draft;
    }

    public function isPending(): bool
    {
        return $this->status === SaleStatus::Pending;
    }

    public function isPaid(): bool
    {
        return $this->status === SaleStatus::Paid;
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleStatus::Cancelled;
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && ! $this->isPaid()
            && ! $this->isCancelled();
    }

    public function hasBalance(): bool
    {
        return (float) $this->balance > 0;
    }

    public function getPaymentProgress(): float
    {
        if ((float) $this->total <= 0) {
            return 100;
        }

        return ((float) $this->paid / (float) $this->total) * 100;
    }

    public function getDaysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->due_date->diffInDays(now());
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function calculateTotals(): void
    {
        $totals = $this->computeTotalsFromLines();

        $this->update($totals + [
            'balance' => $totals['total'] - (float) $this->paid,
        ]);
    }

    public function updatePaymentStatus(): void
    {
        $totalPaid = (float) $this->payments()->sum('amount');
        $total = (float) $this->total;

        $status = match (true) {
            $this->isCancelled() => SaleStatus::Cancelled,
            $totalPaid <= 0 && $this->isDraft() => SaleStatus::Draft,
            $totalPaid >= $total && $total > 0 => SaleStatus::Paid,
            $totalPaid > 0 => SaleStatus::Partial,
            default => SaleStatus::Pending,
        };

        $this->update([
            'paid' => $totalPaid,
            'balance' => $total - $totalPaid,
            'status' => $status,
        ]);

        $this->customer?->recalculateBalance();
    }

    /**
     * Chiffre d'affaires net de la facture : total − avoirs imputés.
     */
    public function getNetTotal(): float
    {
        $creditNotes = (float) $this->creditNotes()
            ->where('status', \App\Enums\CreditNoteStatus::Applied->value)
            ->sum('amount');

        return max(0, (float) $this->total - $creditNotes);
    }

    /**
     * Une facture ayant reçu un paiement ne se modifie plus : on émet un avoir.
     */
    public function isLocked(): bool
    {
        return $this->isCancelled() || $this->payments()->exists();
    }

    public function addPayment(
        float $amount,
        PaymentMethod $method,
        ?int $accountId = null,
        ?string $reference = null,
        ?\DateTimeInterface $paidAt = null,
    ): Payment {
        return DB::transaction(function () use ($amount, $method, $accountId, $reference, $paidAt): Payment {
            // Verrouiller la facture : le solde dû est relu en base avant d'accepter le paiement
            /** @var Sale $locked */
            $locked = static::query()->lockForUpdate()->findOrFail($this->getKey());

            if ($amount > (float) $locked->balance + 0.01) {
                throw CommercialException::paymentExceedsBalance($locked->invoice_number, (float) $locked->balance, $amount);
            }

            /** @var Payment $payment */
            $payment = $this->payments()->create([
                'user_id' => auth()->id(),
                'account_id' => $accountId ?? $this->account_id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'payment_date' => $paidAt ?? now(),
            ]);

            // Créditer le compte financier si configuré, à la date réelle du paiement
            if ($payment->account_id) {
                $payment->account?->credit(
                    amount: $amount,
                    description: "Paiement facture {$this->invoice_number}",
                    category: TransactionCategory::Payment,
                    referenceType: self::class,
                    referenceId: $this->id,
                    referenceNumber: $this->invoice_number,
                    date: $paidAt,
                );

                $payment->update(['is_recorded' => true]);
            }

            $this->updatePaymentStatus();

            return $payment;
        });
    }

    public function cancel(): void
    {
        DB::transaction(function (): void {
            foreach ($this->payments()->whereNotNull('account_id')->get() as $payment) {
                $payment->account?->debit(
                    amount: (float) $payment->amount,
                    description: "Annulation paiement facture {$this->invoice_number}",
                    category: TransactionCategory::Refund,
                    referenceType: self::class,
                    referenceId: $this->id,
                    referenceNumber: $this->invoice_number,
                );
            }

            // Une facture annulée ne doit plus rien : solde à zéro, encours client recalculé
            $this->update(['status' => SaleStatus::Cancelled, 'balance' => 0]);

            $this->customer?->recalculateBalance();

            // Les sujets vivants vendus reviennent dans l'effectif de leur bande
            $this->items()->where('is_live_birds', true)->get()->each->syncFlockQuantities();
        });
    }

    /**
     * Génère le prochain numéro de facture pour l'organisation.
     * En cas de concurrence, le UNIQUE constraint en base protège contre les doublons.
     * Utilise MAX() sur le numéro existant pour être robuste aux suppressions.
     */
    public static function generateInvoiceNumber(int $organisationId): string
    {
        $year = now()->year;
        $sequence = app(\App\Services\NumberSequence::class);

        // Numérotation continue et verrouillée : exigence SYSCOHADA sur les factures
        return $sequence->nextFormatted(
            prefix: 'FAC-',
            key: 'invoice',
            scopeId: $organisationId,
            year: $year,
            seedUsing: fn (): int => $sequence->maxSuffix('sales', 'invoice_number', "FAC-{$year}-", ['organisation_id' => $organisationId]),
        );
    }
}
