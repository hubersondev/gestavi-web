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
        $count = static::where('contract_id', $contractId)->whereYear('created_at', $year)->count();

        return 'AVN-'.$year.'-'.str_pad($count + 1, 3, '0', STR_PAD_LEFT);
    }
}
