<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Vente saisie depuis le mobile : en-tête, lignes et, le cas échéant, encaissement immédiat.
 */
class StoreSaleRequest extends FormRequest
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
            'farm_id' => ['required', 'integer'],
            'customer_id' => ['nullable', 'integer'],
            'sale_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:sale_date'],
            'tax_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'client_uuid' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.unit' => ['nullable', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.flock_id' => ['nullable', 'integer'],
            'items.*.is_live_birds' => ['nullable', 'boolean'],
            'payment' => ['nullable', 'array'],
            'payment.method' => ['required_with:payment', Rule::enum(PaymentMethod::class)],
            'payment.amount' => ['nullable', 'numeric', 'gt:0'],
            'payment.account_id' => ['nullable', 'integer'],
            'payment.reference' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'farm_id.required' => 'La ferme est obligatoire.',
            'sale_date.required' => 'La date de vente est obligatoire.',
            'items.required' => 'Une vente contient au moins une ligne.',
            'items.*.product_name.required' => 'Chaque ligne doit nommer le produit vendu.',
            'items.*.quantity.gt' => 'La quantité doit être positive.',
            'payment.method.required_with' => 'Le mode de paiement est obligatoire pour un encaissement.',
        ];
    }
}
