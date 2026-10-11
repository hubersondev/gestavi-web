<?php

namespace App\Models;

use App\Enums\FiscalYearStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Exercice comptable d'une organisation. Une fois clôturé, aucune écriture ni
 * transaction ne peut plus être datée dans sa période.
 */
class FiscalYear extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id', 'label', 'starts_on', 'ends_on', 'status', 'closed_at', 'closed_by', 'net_result',
    ];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => FiscalYearStatus::class,
            'closed_at' => 'datetime',
            'net_result' => 'decimal:2',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(JournalEntry::class);
    }

    public function scopeCovering(Builder $query, mixed $date): Builder
    {
        $day = \Carbon\Carbon::parse($date)->toDateString();

        return $query->whereDate('starts_on', '<=', $day)->whereDate('ends_on', '>=', $day);
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', FiscalYearStatus::Open->value);
    }

    public function isClosed(): bool
    {
        return $this->status === FiscalYearStatus::Closed;
    }

    public function isOpen(): bool
    {
        return $this->status === FiscalYearStatus::Open;
    }

    public function covers(mixed $date): bool
    {
        $day = \Carbon\Carbon::parse($date)->startOfDay();

        return $this->starts_on->lessThanOrEqualTo($day) && $this->ends_on->greaterThanOrEqualTo($day);
    }
}
