<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractAmendment extends Model
{
    protected $fillable = [
        'contract_id',
        'amendment_number',
        'effective_date',
        'reason',
        'previous_values',
        'new_values',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'previous_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public static function generateNumber(int $contractId): string
    {
        $year = now()->year;
        $sequence = app(\App\Services\NumberSequence::class);

        return $sequence->nextFormatted(
            prefix: 'AVN-',
            key: 'contract_amendment',
            scopeId: $contractId,
            year: $year,
            pad: 3,
            scope: \App\Services\NumberSequence::SCOPE_CONTRACT,
            seedUsing: fn (): int => $sequence->maxSuffix('contract_amendments', 'amendment_number', "AVN-{$year}-", ['contract_id' => $contractId]),
        );
    }
}
