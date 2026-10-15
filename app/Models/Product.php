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
        'name', 'code', 'kind', 'unit', 'stock_units_per_unit',
        'default_unit_price', 'default_tax_percent',
        'is_live_birds', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'kind' => ProductKind::class,
            'stock_units_per_unit' => 'decimal:2',
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
        ['name' => 'Plateau de 30 œufs', 'code' => 'OEUF-P30', 'kind' => 'eggs_tray', 'unit' => 'plateau', 'default_unit_price' => 3000, 'is_live_birds' => false, 'stock_units_per_unit' => 30],
        ['name' => 'Poulet vif', 'code' => 'POUL-VIF', 'kind' => 'live_bird', 'unit' => 'sujet', 'default_unit_price' => 3500, 'is_live_birds' => true, 'stock_units_per_unit' => 1],
        ['name' => 'Poulet abattu', 'code' => 'POUL-ABAT', 'kind' => 'dressed_bird', 'unit' => 'kg', 'default_unit_price' => 2500, 'is_live_birds' => false, 'stock_units_per_unit' => 1],
        ['name' => 'Poule de réforme', 'code' => 'POUL-REF', 'kind' => 'culled_bird', 'unit' => 'sujet', 'default_unit_price' => 2500, 'is_live_birds' => true, 'stock_units_per_unit' => 1],
        ['name' => 'Fumier de volaille', 'code' => 'FUMIER', 'kind' => 'manure', 'unit' => 'sac', 'default_unit_price' => 1500, 'is_live_birds' => false, 'stock_units_per_unit' => 1],
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

    // ─── STOCK SUIVI ─────────────────────────────────────────────────────────

    /**
     * Les œufs sortent du stock d'œufs de la ferme qui vend.
     */
    public function isEggProduct(): bool
    {
        return in_array($this->kind, [ProductKind::EggsTray, ProductKind::EggsUnit], true);
    }

    /**
     * Vrai si une vente de ce produit débite un stock (œufs de la ferme, ou stock associé).
     */
    public function tracksStock(): bool
    {
        return $this->isEggProduct() || $this->stock_id !== null;
    }

    /**
     * Stock débité par une vente de ce produit sur une ferme donnée, null si rien n'est suivi.
     */
    public function resolveStockFor(?int $farmId): ?Stock
    {
        if ($this->isEggProduct()) {
            return $farmId ? Stock::eggStockFor($farmId) : null;
        }

        return $this->stock_id ? $this->stock : null;
    }

    /**
     * Unités de stock correspondant à une quantité vendue (30 œufs par plateau…).
     */
    public function stockUnitsFor(float $soldQuantity): float
    {
        $factor = (float) $this->stock_units_per_unit;

        return round($soldQuantity * ($factor > 0 ? $factor : 1), 2);
    }

    /**
     * Quantité vendable avec le stock disponible, dans l'unité de vente.
     */
    public function sellableQuantityFrom(float $stockQuantity): float
    {
        $factor = (float) $this->stock_units_per_unit;
        $raw = $stockQuantity / ($factor > 0 ? $factor : 1);

        // Un produit groupé (plateau de 30) se vend par unités entières ; sinon deux décimales (kg)
        return $factor > 1 ? floor($raw) : floor($raw * 100) / 100;
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
