<?php

namespace App\Models;

use App\Enums\ProductionKind;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChickenType extends Model
{
    /** @use HasFactory<\Database\Factories\ChickenTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'production_kind',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'production_kind' => ProductionKind::class,
            'is_active' => 'boolean',
        ];
    }

    public function productionKind(): ProductionKind
    {
        return $this->production_kind ?? ProductionKind::Mixed;
    }
}
