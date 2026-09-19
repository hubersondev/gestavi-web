<?php

namespace App\Models;

use App\Enums\IncidentCategory;
use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'flock_id',
        'reported_by',
        'title',
        'incident_date',
        'detected_date',
        'severity',
        'category',
        'symptoms',
        'suspected_disease',
        'confirmed_disease',
        'birds_affected',
        'birds_dead',
        'treatment_applied',
        'treatment_start_date',
        'treatment_end_date',
        'treatment_cost',
        'quarantine_applied',
        'quarantine_start_date',
        'quarantine_end_date',
        'status',
        'resolution_date',
        'resolution_notes',
        'lessons_learned',
        'preventive_measures',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'severity' => IncidentSeverity::class,
            'category' => IncidentCategory::class,
            'status' => IncidentStatus::class,
            'incident_date' => 'date',
            'detected_date' => 'date',
            'treatment_start_date' => 'date',
            'treatment_end_date' => 'date',
            'quarantine_start_date' => 'date',
            'quarantine_end_date' => 'date',
            'resolution_date' => 'date',
            'treatment_cost' => 'decimal:2',
            'quarantine_applied' => 'boolean',
        ];
    }

    // ===== RELATIONS =====

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    // ===== SCOPES =====

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            IncidentStatus::Detected,
            IncidentStatus::UnderTreatment,
            IncidentStatus::Monitoring,
        ]);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', IncidentStatus::Resolved);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', IncidentSeverity::Critical);
    }

    public function scopeHighSeverity(Builder $query): Builder
    {
        return $query->whereIn('severity', [IncidentSeverity::High, IncidentSeverity::Critical]);
    }

    public function scopeWithQuarantine(Builder $query): Builder
    {
        return $query->where('quarantine_applied', true);
    }

    public function scopeForCategory(Builder $query, IncidentCategory|string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeForFlock(Builder $query, int $flockId): Builder
    {
        return $query->where('flock_id', $flockId);
    }

    // ===== HELPERS =====

    public function isDetected(): bool
    {
        return $this->status === IncidentStatus::Detected;
    }

    public function isUnderTreatment(): bool
    {
        return $this->status === IncidentStatus::UnderTreatment;
    }

    public function isMonitoring(): bool
    {
        return $this->status === IncidentStatus::Monitoring;
    }

    public function isResolved(): bool
    {
        return $this->status === IncidentStatus::Resolved;
    }

    public function isChronic(): bool
    {
        return $this->status === IncidentStatus::Chronic;
    }

    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    public function isCritical(): bool
    {
        return $this->severity === IncidentSeverity::Critical;
    }

    public function isHighSeverity(): bool
    {
        return in_array($this->severity, [IncidentSeverity::High, IncidentSeverity::Critical]);
    }

    public function hasQuarantine(): bool
    {
        return $this->quarantine_applied;
    }

    public function isInQuarantine(): bool
    {
        return $this->quarantine_applied
            && $this->quarantine_start_date?->isPast()
            && (! $this->quarantine_end_date || $this->quarantine_end_date->isFuture());
    }

    /**
     * Taux de mortalité lié à cet incident.
     */
    public function getMortalityRate(): ?float
    {
        if (! $this->birds_affected || $this->birds_affected <= 0) {
            return null;
        }

        return round(($this->birds_dead / $this->birds_affected) * 100, 2);
    }

    /**
     * Durée du traitement en jours.
     */
    public function getTreatmentDuration(): ?int
    {
        if (! $this->treatment_start_date) {
            return null;
        }

        $end = $this->treatment_end_date ?? now();

        return (int) $this->treatment_start_date->diffInDays($end);
    }

    /**
     * Durée de la quarantaine en jours.
     */
    public function getQuarantineDuration(): ?int
    {
        if (! $this->quarantine_applied || ! $this->quarantine_start_date) {
            return null;
        }

        $end = $this->quarantine_end_date ?? now();

        return (int) $this->quarantine_start_date->diffInDays($end);
    }

    /**
     * Démarrer le traitement.
     */
    public function startTreatment(string $treatment, ?string $startDate = null): void
    {
        $this->update([
            'status' => IncidentStatus::UnderTreatment,
            'treatment_applied' => $treatment,
            'treatment_start_date' => $startDate ?? now()->toDateString(),
        ]);
    }

    /**
     * Passer en surveillance.
     */
    public function startMonitoring(): void
    {
        $this->update(['status' => IncidentStatus::Monitoring]);
    }

    /**
     * Résoudre l'incident.
     */
    public function resolve(?string $notes = null): void
    {
        $this->update([
            'status' => IncidentStatus::Resolved,
            'resolution_date' => now()->toDateString(),
            'resolution_notes' => $notes ?? $this->resolution_notes,
        ]);
    }
}
