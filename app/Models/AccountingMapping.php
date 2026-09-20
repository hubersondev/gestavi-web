<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Imputation comptable : clé fonctionnelle (ex. `expense.feed`) → compte du plan.
 * Une organisation peut surcharger le paramétrage par défaut (organisation_id NULL).
 */
class AccountingMapping extends Model
{
    protected $fillable = [
        'organisation_id', 'mapping_key', 'chart_account_code', 'description',
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }
}
