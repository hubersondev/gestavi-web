<?php

namespace App\Models;

use App\Enums\FarmLogCategory;
use App\Enums\FarmLogPriority;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmLog extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'farm_id',
        'flock_id',
        'building_id',
        'recorded_by',
        'resolved_by',
        'log_date',
        'log_time',
        'title',
        'category',
        'priority',
        'description',
        'actions_taken',
        'attachments',
        'weather',
        'temperature',
        'humidity',
        'requires_follow_up',
        'follow_up_date',
        'is_resolved',
        'resolved_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'category' => FarmLogCategory::class,
            'priority' => FarmLogPriority::class,
            'log_date' => 'date',
            'follow_up_date' => 'date',
            'resolved_at' => 'datetime',
            'temperature' => 'decimal:1',
            'humidity' => 'decimal:1',
            'attachments' => 'array',
            'requires_follow_up' => 'boolean',
            'is_resolved' => 'boolean',
        ];
    }

    // ===== RELATIONS =====

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    // ===== SCOPES =====

    public function scopeForDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('log_date', $date);
    }

    public function scopeForDateRange(Builder $query, string $from, string $to): Builder
    {
        return $query->whereBetween('log_date', [$from, $to]);
    }

    public function scopeForCategory(Builder $query, FarmLogCategory|string $category): Builder
    {
        return $query->where('category', $category instanceof FarmLogCategory ? $category->value : $category);
    }

    public function scopeForPriority(Builder $query, FarmLogPriority|string $priority): Builder
    {
        return $query->where('priority', $priority instanceof FarmLogPriority ? $priority->value : $priority);
    }

    public function scopeUrgent(Builder $query): Builder
    {
        return $query->where('priority', FarmLogPriority::Urgent->value);
    }

    public function scopeHigh(Builder $query): Builder
    {
        return $query->where('priority', FarmLogPriority::High->value);
    }

    public function scopeRequiresFollowUp(Builder $query): Builder
    {
        return $query->where('requires_follow_up', true);
    }

    public function scopeOverdueFollowUp(Builder $query): Builder
    {
        return $query
            ->where('requires_follow_up', true)
            ->where('is_resolved', false)
            ->where('follow_up_date', '<', now()->toDateString());
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('is_resolved', true);
    }

    // ===== HELPERS =====

    public function isUrgent(): bool
    {
        return $this->priority === FarmLogPriority::Urgent;
    }

    public function isHighPriority(): bool
    {
        return $this->priority === FarmLogPriority::High;
    }

    public function requiresFollowUp(): bool
    {
        return $this->requires_follow_up;
    }

    public function isOverdueFollowUp(): bool
    {
        return $this->requires_follow_up
            && ! $this->is_resolved
            && $this->follow_up_date
            && $this->follow_up_date->isPast();
    }

    public function isResolved(): bool
    {
        return $this->is_resolved;
    }

    public function hasAttachments(): bool
    {
        return ! empty($this->attachments);
    }

    public function getAttachmentsCount(): int
    {
        return count($this->attachments ?? []);
    }

    // ===== ACTIONS =====

    public function resolve(int $resolvedBy, ?string $notes = null): void
    {
        $this->update([
            'is_resolved' => true,
            'resolved_at' => now(),
            'resolved_by' => $resolvedBy,
            'notes' => $notes ?? $this->notes,
        ]);
    }

    public function reopen(): void
    {
        $this->update([
            'is_resolved' => false,
            'resolved_at' => null,
            'resolved_by' => null,
        ]);
    }
}
