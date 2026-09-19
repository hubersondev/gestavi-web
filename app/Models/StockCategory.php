<?php

namespace App\Models;

use App\Enums\StockCategoryType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class StockCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'organisation_id',
        'name', 'slug', 'type', 'description',
    ];

    protected function casts(): array
    {
        return [
            'type' => StockCategoryType::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (StockCategory $category): void {
            if (empty($category->slug) && ! empty($category->name)) {
                $base = Str::slug($category->name);
                $slug = $base;
                $counter = 1;
                while (static::where('organisation_id', $category->organisation_id)->where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$counter++;
                }
                $category->slug = $slug;
            }
        });
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function scopeForOrganisation(Builder $query, int $organisationId): Builder
    {
        return $query->where('organisation_id', $organisationId);
    }

    public function scopeOfType(Builder $query, StockCategoryType|string $type): Builder
    {
        return $query->where('type', $type instanceof StockCategoryType ? $type->value : $type);
    }
}
