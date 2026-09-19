<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\LogsAuditTrail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, LogsAuditTrail;

    protected $fillable = [
        'sale_id', 'account_id', 'user_id',
        'amount', 'method', 'reference', 'payment_date',
        'is_recorded', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'is_recorded' => 'boolean',
        ];
    }

    // ─── RELATIONS ───────────────────────────────────────────────────────────

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
