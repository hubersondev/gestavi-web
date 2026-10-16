<?php

namespace App\Models;

use App\Enums\PredictionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Valeur prévue pour une bande à une date cible (mortalité cumulée, taux de ponte, poids,
 * aliment par sujet), avec la confiance et les facteurs qui l'expliquent. Une fois la date
 * passée, la valeur réelle et l'écart sont renseignés pour mesurer la précision.
 */
class FlockPrediction extends Model
{
    protected $fillable = [
        'organisation_id', 'farm_id', 'flock_id', 'type',
        'prediction_date', 'target_date', 'target_age_days',
        'predicted_value', 'confidence', 'standard_value',
        'actual_value', 'variance', 'variance_percent',
        'factors', 'model_version',
    ];

    protected function casts(): array
    {
        return [
            'type' => PredictionType::class,
            'prediction_date' => 'date',
            'target_date' => 'date',
            'predicted_value' => 'decimal:2',
            'confidence' => 'decimal:2',
            'standard_value' => 'decimal:2',
            'actual_value' => 'decimal:2',
            'variance' => 'decimal:2',
            'variance_percent' => 'decimal:2',
            'factors' => 'array',
        ];
    }

    public function flock(): BelongsTo
    {
        return $this->belongsTo(Flock::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('actual_value');
    }

    public function scopeEvaluated(Builder $query): Builder
    {
        return $query->whereNotNull('actual_value');
    }

    public function scopeForType(Builder $query, PredictionType|string $type): Builder
    {
        return $query->where('type', $type instanceof PredictionType ? $type->value : $type);
    }

    /**
     * Renseigne la valeur réelle et l'écart (en valeur et en % de la prévision).
     */
    public function recordActual(float $actual): void
    {
        $predicted = (float) $this->predicted_value;
        $variance = $actual - $predicted;

        $this->update([
            'actual_value' => $actual,
            'variance' => round($variance, 2),
            'variance_percent' => $predicted != 0.0 ? round(abs($variance) / abs($predicted) * 100, 2) : null,
        ]);
    }

    /**
     * excellent (≤ 5 %), good (≤ 10 %), fair (≤ 20 %), poor, ou pending sans valeur réelle.
     */
    public function accuracyLevel(): string
    {
        if ($this->actual_value === null || $this->variance_percent === null) {
            return 'pending';
        }

        $variance = (float) $this->variance_percent;

        return match (true) {
            $variance <= 5 => 'excellent',
            $variance <= 10 => 'good',
            $variance <= 20 => 'fair',
            default => 'poor',
        };
    }

    public static function accuracyLabel(string $level): string
    {
        return match ($level) {
            'excellent' => 'Excellente',
            'good' => 'Bonne',
            'fair' => 'Moyenne',
            'poor' => 'Faible',
            default => 'En attente',
        };
    }

    public function confidenceLabel(): string
    {
        $confidence = (float) $this->confidence;

        return match (true) {
            $confidence >= 80 => 'Haute',
            $confidence >= 50 => 'Moyenne',
            default => 'Basse',
        };
    }
}
