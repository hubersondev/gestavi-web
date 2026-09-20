<?php

namespace App\Models;

use App\Enums\VetVisitType;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VetVisit extends Model
{
    use BelongsToOrganisation, HasFactory;

    protected $fillable = [
        'organisation_id',
        'farm_id',
        'flock_id',
        'veterinarian_id',
        'recorded_by',
        'parent_visit_id',
        'visit_date',
        'visit_time',
        'visit_type',
        'reason',
        'symptoms_observed',
        'diagnosis',
        'birds_examined',
        'birds_affected',
        'samples_taken',
        'samples_description',
        'lab_results',
        'prescriptions',
        'recommendations',
        'preventive_measures',
        'consultation_fee',
        'medication_cost',
        'total_cost',
        'is_paid',
        'paid_at',
        'follow_up_required',
        'next_visit_date',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'visit_type' => VetVisitType::class,
            'visit_date' => 'date',
            'paid_at' => 'date',
            'next_visit_date' => 'date',
            'consultation_fee' => 'decimal:2',
            'medication_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'samples_taken' => 'boolean',
            'is_paid' => 'boolean',
            'follow_up_required' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (VetVisit $visit) {
            $fee = (float) ($visit->consultation_fee ?? 0);
            $meds = (float) ($visit->medication_cost ?? 0);

            if ($fee > 0 || $meds > 0) {
                $visit->total_cost = $fee + $meds;
            }
        });
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

    public function veterinarian(): BelongsTo
    {
        return $this->belongsTo(Veterinarian::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function parentVisit(): BelongsTo
    {
        return $this->belongsTo(VetVisit::class, 'parent_visit_id');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(VetVisit::class, 'parent_visit_id');
    }

    // ===== SCOPES =====

    public function scopeRoutine(Builder $query): Builder
    {
        return $query->where('visit_type', VetVisitType::Routine);
    }

    public function scopeEmergency(Builder $query): Builder
    {
        return $query->where('visit_type', VetVisitType::Emergency);
    }

    public function scopeFollowUp(Builder $query): Builder
    {
        return $query->where('visit_type', VetVisitType::FollowUp);
    }

    public function scopeWithFollowUpRequired(Builder $query): Builder
    {
        return $query->where('follow_up_required', true);
    }

    public function scopeOverdueFollowUp(Builder $query): Builder
    {
        return $query
            ->where('follow_up_required', true)
            ->where('next_visit_date', '<', now()->toDateString());
    }

    // ===== HELPERS =====

    public function isEmergency(): bool
    {
        return $this->visit_type === VetVisitType::Emergency;
    }

    public function requiresFollowUp(): bool
    {
        return $this->follow_up_required;
    }

    public function hasSamples(): bool
    {
        return $this->samples_taken;
    }

    public function hasDiagnosis(): bool
    {
        return ! empty($this->diagnosis);
    }

    public function isPaid(): bool
    {
        return $this->is_paid;
    }

    /**
     * Taux de sujets affectés sur ceux examinés.
     */
    public function getAffectedRate(): ?float
    {
        if (! $this->birds_examined || $this->birds_examined <= 0) {
            return null;
        }

        return round(((int) $this->birds_affected / $this->birds_examined) * 100, 1);
    }

    /**
     * Nombre de jours avant la prochaine visite.
     */
    public function getDaysUntilNextVisit(): ?int
    {
        if (! $this->next_visit_date) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($this->next_visit_date, false);
    }

    /**
     * Marquer la visite comme payée.
     */
    public function markAsPaid(): void
    {
        $this->update([
            'is_paid' => true,
            'paid_at' => now()->toDateString(),
        ]);
    }
}
