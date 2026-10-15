<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'kind' => $this->kind?->value,
            'unit' => $this->unit,
            'default_unit_price' => (float) $this->default_unit_price,
            'default_tax_percent' => (float) $this->default_tax_percent,
            'is_live_birds' => (bool) $this->is_live_birds,
            'stock_units_per_unit' => (float) ($this->stock_units_per_unit ?: 1),
            'stock_kind' => $this->isEggProduct() ? 'eggs' : null,
            'stock_id' => $this->isEggProduct() ? null : $this->stock_id,
            'is_active' => (bool) $this->is_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
