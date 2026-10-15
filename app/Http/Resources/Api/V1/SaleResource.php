<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Facture de vente avec ses lignes et ses encaissements.
 *
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'invoice_number' => $this->invoice_number,
            'farm_id' => $this->farm_id,
            'customer_id' => $this->customer_id,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer?->name),
            'sale_date' => $this->sale_date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'status' => $this->status?->value,
            'subtotal' => (float) $this->subtotal,
            'tax_percent' => (float) $this->tax_percent,
            'tax_amount' => (float) $this->tax_amount,
            'total' => (float) $this->total,
            'paid' => (float) $this->paid,
            'balance' => (float) $this->balance,
            'notes' => $this->notes,
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item): array => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'unit' => $item->unit,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
                'flock_id' => $item->flock_id,
                'is_live_birds' => (bool) $item->is_live_birds,
            ])->values()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn ($payment): array => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'method' => $payment->method instanceof \BackedEnum ? $payment->method->value : $payment->method,
                'account_id' => $payment->account_id,
                'reference' => $payment->reference,
                'payment_date' => $payment->payment_date?->toDateString(),
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
