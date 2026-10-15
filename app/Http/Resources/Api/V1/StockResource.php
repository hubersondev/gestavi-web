<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Stock
 */
class StockResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'farm_id' => $this->farm_id,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'category_type' => $this->whenLoaded('category', fn () => $this->category?->type instanceof \BackedEnum ? $this->category->type->value : $this->category?->type),
            'unit' => $this->unit,
            'quantity' => (float) $this->quantity,
            'min_quantity' => (float) $this->min_quantity,
            'is_low' => (float) $this->quantity <= (float) $this->min_quantity,
            'unit_price' => (float) $this->unit_price,
            'expiry_date' => $this->expiry_date?->toDateString(),
            'location' => $this->location,
            'is_active' => (bool) $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
