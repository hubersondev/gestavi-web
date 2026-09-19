<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Veterinarian extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'name',
        'phone',
        'email',
        'clinic',
        'license',
        'specialty',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // ===== RELATIONS =====

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function vetVisits(): HasMany
    {
        return $this->hasMany(VetVisit::class);
    }

    // ===== SCOPES =====

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ===== HELPERS =====

    public function getFullLabel(): string
    {
        $label = $this->name;

        if ($this->clinic) {
            $label .= ' — '.$this->clinic;
        }

        return $label;
    }
}
