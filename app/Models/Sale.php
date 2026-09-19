<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\TransactionCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

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
        $subtotal = (float) $this->items()->sum('total');
        $discountAmount = (float) $this->discount_percent > 0
            ? $subtotal * ((float) $this->discount_percent / 100)
            : (float) ($this->discount_amount ?? 0);
        $afterDiscount = $subtotal - $discountAmount;
        $taxAmount = (float) $this->tax_percent > 0
            ? $afterDiscount * ((float) $this->tax_percent / 100)
            : 0;
        $total = $afterDiscount + $taxAmount;

        $this->update([
            'subtotal' => $subtotal,
            'discount_amount' => $discountAmount,
            'tax_amount' => $taxAmount,
            'total' => $total,
            'balance' => $total - (float) $this->paid,
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

        if ($this->customer) {
            $this->customer->update([
                'balance' => $this->customer->sales()->sum('balance'),
            ]);
        }
    }

    public function addPayment(
        float $amount,
        PaymentMethod $method,
        ?int $accountId = null,
        ?string $reference = null,
        ?\DateTimeInterface $paidAt = null,
    ): Payment {
        return DB::transaction(function () use ($amount, $method, $accountId, $reference, $paidAt): Payment {
            /** @var Payment $payment */
            $payment = $this->payments()->create([
                'user_id' => auth()->id(),
                'account_id' => $accountId ?? $this->account_id,
                'amount' => $amount,
                'method' => $method,
                'reference' => $reference,
                'payment_date' => $paidAt ?? now(),
            ]);

            // Créditer le compte financier si configuré
            if ($payment->account_id) {
                $payment->account?->credit(
                    amount: $amount,
                    description: "Paiement facture {$this->invoice_number}",
                    category: TransactionCategory::Payment,
                    referenceType: self::class,
                    referenceId: $this->id,
                    referenceNumber: $this->invoice_number,
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

            if ($this->customer) {
                $this->customer->subtractFromBalance((float) $this->balance);
            }

            $this->update(['status' => SaleStatus::Cancelled]);
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
        $prefix = "FAC-{$year}-";

        $lastNumber = (int) static::withTrashed()
            ->where('organisation_id', $organisationId)
            ->where('invoice_number', 'like', "{$prefix}%")
            ->selectRaw("MAX(CAST(SUBSTRING(invoice_number FROM '.{5}$') AS INTEGER)) as max_num")
            ->value('max_num');

        return sprintf('%s%05d', $prefix, $lastNumber + 1);
    }
}
