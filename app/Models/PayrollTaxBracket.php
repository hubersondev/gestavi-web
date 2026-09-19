<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tranche du barème progressif ITS : taux appliqué à la part de l'assiette
 * comprise entre lower_bound (exclu) et upper_bound (inclus, NULL = sans limite).
 */
class PayrollTaxBracket extends Model
{
    protected $fillable = [
        'payroll_parameter_id', 'lower_bound', 'upper_bound', 'rate', 'order',
    ];

    protected function casts(): array
    {
        return [
            'lower_bound' => 'decimal:2',
            'upper_bound' => 'decimal:2',
            'rate' => 'decimal:4',
            'order' => 'integer',
        ];
    }

    public function parameter(): BelongsTo
    {
        return $this->belongsTo(PayrollParameter::class, 'payroll_parameter_id');
    }

    /**
     * Montant d'impôt dû sur cette tranche pour une assiette donnée.
     */
    public function taxFor(float $taxableBase): float
    {
        $lower = (float) $this->lower_bound;
        $upper = $this->upper_bound === null ? PHP_FLOAT_MAX : (float) $this->upper_bound;

        if ($taxableBase <= $lower) {
            return 0.0;
        }

        return (min($taxableBase, $upper) - $lower) * (float) $this->rate;
    }
}
