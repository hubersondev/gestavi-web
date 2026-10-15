<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\StockMovementReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Entrée ou sortie de stock saisie depuis le mobile (achat reçu, consommation, perte, ajustement).
 */
class StoreStockMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'stock_id' => ['required', 'integer'],
            'direction' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', Rule::enum(StockMovementReason::class)],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'flock_id' => ['nullable', 'integer'],
            'reference' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_uuid' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'stock_id.required' => 'L\'article de stock est obligatoire.',
            'direction.in' => 'Le sens du mouvement est « in » (entrée) ou « out » (sortie).',
            'quantity.gt' => 'La quantité doit être positive.',
        ];
    }
}
