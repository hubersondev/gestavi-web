<?php

namespace App\Models;

use App\Enums\PaymentTerms;
use App\Enums\PurchaseOrderStatus;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use BelongsToOrganisation, HasFactory, LogsAuditTrail, SoftDeletes;

    protected $fillable = [
        'organisation_id', 'farm_id', 'supplier_id',
        'created_by', 'approved_by',
        'order_number', 'order_date',
        'expected_delivery_date', 'actual_delivery_date',
        'status',
        'subtotal', 'tax_amount', 'discount_amount', 'shipping_cost', 'total',
        'payment_terms', 'payment_terms_details',
        'delivery_address', 'delivery_contact', 'delivery_phone',
        'supplier_quote_ref', 'supplier_order_ref',
        'notes', 'internal_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'payment_terms' => PaymentTerms::class,
            'order_date' => 'date',
            'expected_delivery_date' => 'date',
            'actual_delivery_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'shipping_cost' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $order): void {
            if ($order->organisation_id === null && $order->farm_id !== null) {
                $order->organisation_id = Farm::find($order->farm_id)?->organisation_id;
            }

            if (empty($order->order_number) && $order->organisation_id) {
                $order->order_number = self::generateOrderNumber($order->organisation_id);
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

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    // ─── SCOPES ──────────────────────────────────────────────────────────────

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', PurchaseOrderStatus::Draft);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereIn('status', [PurchaseOrderStatus::Draft, PurchaseOrderStatus::PendingApproval, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent]);
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('status', PurchaseOrderStatus::Received);
    }

    // ─── HELPERS ─────────────────────────────────────────────────────────────

    public function isDraft(): bool
    {
        return $this->status === PurchaseOrderStatus::Draft;
    }

    public function isReceived(): bool
    {
        return $this->status === PurchaseOrderStatus::Received;
    }

    public function canBeCancelled(): bool
    {
        return ! in_array($this->status, [PurchaseOrderStatus::Received, PurchaseOrderStatus::Cancelled], true);
    }

    public function canBeApproved(): bool
    {
        return in_array($this->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::PendingApproval], true);
    }

    public function canBeReceived(): bool
    {
        return in_array($this->status, [PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent, PurchaseOrderStatus::PartiallyReceived], true);
    }

    // ─── ACTIONS ─────────────────────────────────────────────────────────────

    public function calculateTotals(): void
    {
        $subtotal = (float) $this->items()->sum('total');
        $total = $subtotal + (float) $this->tax_amount + (float) $this->shipping_cost - (float) $this->discount_amount;

        $this->update([
            'subtotal' => $subtotal,
            'total' => max(0, $total),
        ]);
    }

    public function updateReceptionStatus(): void
    {
        $items = $this->items;

        if ($items->isEmpty()) {
            return;
        }

        $allReceived = $items->every(fn ($item) => (float) $item->quantity_received >= (float) $item->quantity_ordered);
        $anyReceived = $items->contains(fn ($item) => (float) $item->quantity_received > 0);

        $newStatus = match (true) {
            $allReceived => PurchaseOrderStatus::Received,
            $anyReceived => PurchaseOrderStatus::PartiallyReceived,
            default => $this->status,
        };

        if ($newStatus !== $this->status) {
            $this->update([
                'status' => $newStatus,
                'actual_delivery_date' => $allReceived ? now()->toDateString() : $this->actual_delivery_date,
            ]);
        }
    }

    public static function generateOrderNumber(int $organisationId): string
    {
        $year = now()->year;
        $sequence = app(\App\Services\NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'BC-',
            key: 'purchase_order',
            scopeId: $organisationId,
            year: $year,
            seedUsing: fn (): int => $sequence->maxSuffix('purchase_orders', 'order_number', "BC-{$year}-", ['organisation_id' => $organisationId]),
        );
    }
}
