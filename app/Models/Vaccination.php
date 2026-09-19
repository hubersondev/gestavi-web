<?php

namespace App\Models;

use App\Enums\VaccinationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccination extends Model
{
    use HasFactory;

    protected $fillable = [
        'flock_id',
        'health_program_id',
        'administered_by',
        'treatment_name',
        'treatment_type',
        'scheduled_date',
        'administered_date',
        'age_at_treatment',
        'administration_route',
        'dosage',
        'batch_number',
        'expiry_date',
        'birds_treated',
        'cost',
        'status',
        'observations',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => VaccinationStatus::class,
            'scheduled_date' => 'date',
            'administered_date' => 'date',
            'expiry_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    // ===== RELATIONS =====

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function healthProgram(): BelongsTo
    {
        return $this->belongsTo(HealthProgram::class);
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    // ===== SCOPES =====

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Pending);
    }

    public function scopeDone(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Done);
    }

    public function scopeSkipped(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Skipped);
    }

    public function scopeDelayed(Builder $query): Builder
    {
        return $query->where('status', VaccinationStatus::Delayed);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [VaccinationStatus::Pending, VaccinationStatus::Delayed])
            ->where('scheduled_date', '<', now()->toDateString());
    }

    public function scopeUpcoming(Builder $query, int $days = 7): Builder
    {
        return $query
            ->where('status', VaccinationStatus::Pending)
            ->whereBetween('scheduled_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeVaccins(Builder $query): Builder
    {
        return $query->where('treatment_type', 'vaccin');
    }

    // ===== HELPERS =====

    public function isPending(): bool
    {
        return $this->status === VaccinationStatus::Pending;
    }

    public function isDone(): bool
    {
        return $this->status === VaccinationStatus::Done;
    }

    public function isSkipped(): bool
    {
        return $this->status === VaccinationStatus::Skipped;
    }

    public function isDelayed(): bool
    {
        return $this->status === VaccinationStatus::Delayed;
    }

    public function isOverdue(): bool
    {
        return $this->isPending() && $this->scheduled_date?->isPast();
    }

    public function isVaccin(): bool
    {
        return $this->treatment_type === 'vaccin';
    }

    public function isActionable(): bool
    {
        return $this->isPending() || $this->isDelayed();
    }

    /**
     * Nombre de jours avant la date prévue (négatif si en retard).
     */
    public function getDaysUntilScheduled(): ?int
    {
        if (! $this->scheduled_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->scheduled_date, false);
    }

    /**
     * Nombre de jours de retard.
     */
    public function getDaysOverdue(): int
    {
        if (! $this->isOverdue()) {
            return 0;
        }

        return (int) $this->scheduled_date->diffInDays(now());
    }

    /**
     * Coût par sujet traité.
     */
    public function getCostPerBird(): ?float
    {
        if (! $this->cost || ! $this->birds_treated || $this->birds_treated <= 0) {
            return null;
        }

        return round((float) $this->cost / $this->birds_treated, 2);
    }

    /**
     * Marquer la vaccination comme effectuée.
     */
    public function markAsDone(array $data = []): void
    {
        $this->update(array_merge([
            'status' => VaccinationStatus::Done,
            'administered_date' => $data['administered_date'] ?? now()->toDateString(),
        ], $data));
    }

    /**
     * Marquer la vaccination comme ignorée.
     */
    public function markAsSkipped(string $reason = ''): void
    {
        $this->update([
            'status' => VaccinationStatus::Skipped,
            'notes' => $reason ?: $this->notes,
        ]);
    }

    /**
     * Reporter la vaccination à une nouvelle date.
     */
    public function reschedule(\Carbon\Carbon $newDate, string $reason = ''): void
    {
        $this->update([
            'status' => VaccinationStatus::Delayed,
            'scheduled_date' => $newDate,
            'notes' => $reason ?: $this->notes,
        ]);
    }
}
