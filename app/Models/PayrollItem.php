<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'payroll_id', 'type', 'label', 'amount',
        'is_deduction', 'sort_order', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'is_deduction' => 'boolean',
        ];
    }

    public function payroll(): BelongsTo
    {
        return $this->belongsTo(Payroll::class);
    }

    public function isDeduction(): bool
    {
        return $this->is_deduction;
    }

    public function getSignedAmount(): float
    {
        return $this->is_deduction ? -(float) $this->amount : (float) $this->amount;
    }
}
