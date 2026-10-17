<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Réglage de la plateforme modifiable en interface (clé → valeur JSON).
 */
class PlatformSetting extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value', 'updated_by'];

    protected function casts(): array
    {
        return ['value' => 'array'];
    }
}
