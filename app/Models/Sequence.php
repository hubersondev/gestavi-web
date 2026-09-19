<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Compteur de numérotation par périmètre (organisation, ferme, employé, contrat),
 * clé fonctionnelle et année. Manipulé exclusivement via NumberSequence.
 */
class Sequence extends Model
{
    protected $fillable = [
        'scope',
        'scope_id',
        'key',
        'year',
        'last_value',
    ];

    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'year' => 'integer',
            'last_value' => 'integer',
        ];
    }
}
