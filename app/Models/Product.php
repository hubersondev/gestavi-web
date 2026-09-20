<?php

namespace App\Models;

use App\Enums\ProductKind;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Produit du catalogue commercial de l'organisation.
 */
class Product extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'organisation_id', 'stock_id',
        'name', 'code', 'kind', 'unit',
        'default_unit_price', 'default_tax_percent',
        'is_live_birds', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'default_unit_price' => 'decimal:2',
            'default_tax_percent' => 'decimal:2',
            'is_live_birds' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Produits proposés par défaut à chaque nouvelle organisation.
     *
     * @var list<array<string, mixed>>
     */
    public const DEFAULT_PRODUCTS = [
        ['name' => 'Plateau de 30 œufs', 'code' => 'OEUF-P30', 'kind' => 'eggs_tray', 'unit' => 'plateau', 'default_unit_price' => 3000, 'is_live_birds' => false],
        ['name' => 'Poulet vif', 'code' => 'POUL-VIF', 'kind' => 'live_bird', 'unit' => 'sujet', 'default_unit_price' => 3500, 'is_live_birds' => true],
        ['name' => 'Poulet abattu', 'code' => 'POUL-ABAT', 'kind' => 'dressed_bird', 'unit' => 'kg', 'default_unit_price' => 2500, 'is_live_birds' => false],
        ['name' => 'Poule de réforme', 'code' => 'POUL-REF', 'kind' => 'culled_bird', 'unit' => 'sujet', 'default_unit_price' => 2500, 'is_live_birds' => true],
        ['name' => 'Fumier de volaille', 'code' => 'FUMIER', 'kind' => 'manure', 'unit' => 'sac', 'default_unit_price' => 1500, 'is_live_birds' => false],
    ];

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class);
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(Stock::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Valeurs à pré-remplir sur une ligne de vente ou de devis.
     *
     * @return array<string, mixed>
     */
    public function lineDefaults(): array
    {
        return [
            'product_name' => $this->name,
            'unit' => $this->unit,
            'unit_price' => (float) $this->default_unit_price,
            'is_live_birds' => $this->is_live_birds,
        ];
    }
}
