<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Agrégat quotidien par ferme (production, ventes, encaissements, dépenses),
 * calculé par FarmMetricsAggregator.
 */
class FarmDailyMetric extends Model
{
    protected $fillable = [
        'organisation_id', 'farm_id', 'metric_date',
        'active_flocks', 'birds_present', 'mortality', 'culls',
        'eggs_collected', 'eggs_broken', 'feed_kg', 'water_l',
        'sales_total', 'payments_total', 'expenses_total',
        'computed_at',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'active_flocks' => 'integer',
            'birds_present' => 'integer',
            'mortality' => 'integer',
            'culls' => 'integer',
            'eggs_collected' => 'integer',
            'eggs_broken' => 'integer',
            'feed_kg' => 'decimal:2',
            'water_l' => 'decimal:2',
            'sales_total' => 'decimal:2',
            'payments_total' => 'decimal:2',
            'expenses_total' => 'decimal:2',
            'computed_at' => 'datetime',
        ];
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class);
    }
}
