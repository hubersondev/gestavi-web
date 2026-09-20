<?php

namespace App\Http\Requests\Offline;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Lot de saisies terrain à synchroniser.
 *
 * Corps attendu : `{"entries": [{client_uuid, type, payload, recorded_at}]}`.
 */
class SyncRequest extends FormRequest
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
            'entries' => ['required', 'array', 'min:1', 'max:200'],
            'entries.*.client_uuid' => ['required', 'uuid'],
            'entries.*.type' => ['required', 'string', 'in:daily_record,vaccination_done'],
            'entries.*.payload' => ['required', 'array'],
            'entries.*.recorded_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'entries.required' => 'Aucune saisie à synchroniser.',
            'entries.*.client_uuid.uuid' => 'Identifiant de saisie invalide.',
            'entries.*.type.in' => 'Type de saisie inconnu.',
        ];
    }
}
