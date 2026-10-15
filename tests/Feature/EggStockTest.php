<?php

use App\Enums\StockCategoryType;
use App\Exceptions\CommercialException;
use App\Models\Building;
use App\Models\DailyRecord;
use App\Models\Farm;
use App\Models\Flock;
use App\Models\Organisation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Stock;
use App\Services\SaleService;

/**
 * Stock d'œufs : les relevés l'alimentent, les ventes de plateaux en sortent, une vente
 * au-delà du disponible est refusée, l'annulation restitue, et les fermes existantes reprennent
 * leur historique.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
    $building = Building::factory()->create(['farm_id' => $this->farm->id]);
    $this->flock = Flock::factory()->active()->create([
        'building_id' => $building->id,
        'quantity_started' => 1000,
        'quantity_current' => 1000,
        'start_date' => now()->subDays(150),
    ]);
    $this->trays = Product::query()->where('organisation_id', $this->organisation->id)->where('code', 'OEUF-P30')->firstOrFail();
    $this->service = app(SaleService::class);
});

function eggStock(Farm $farm): Stock
{
    return Stock::eggStockFor($farm->id)->fresh();
}

function collectEggs(Flock $flock, int $collected, int $broken = 0, ?string $date = null): DailyRecord
{
    return DailyRecord::create([
        'organisation_id' => $flock->organisation_id,
        'farm_id' => $flock->farm_id,
        'flock_id' => $flock->id,
        'record_date' => $date ?? now()->subDay()->toDateString(),
        'mortality' => 0,
        'culls' => 0,
        'eggs_collected' => $collected,
        'eggs_broken' => $broken,
    ]);
}

it('crée un stock d\'œufs par ferme, dans une catégorie de type production, et le plateau vaut 30 œufs', function () {
    $stock = eggStock($this->farm);

    expect($stock->unit)->toBe('œuf')
        ->and($stock->category->type)->toBe(StockCategoryType::Production)
        ->and((float) $stock->quantity)->toBe(0.0)
        ->and((float) $this->trays->stock_units_per_unit)->toBe(30.0)
        ->and(Stock::query()->where('farm_id', $this->farm->id)->where('kind', Stock::KIND_EGGS)->count())->toBe(1);
});

it('alimente le stock avec les œufs collectés, ajuste l\'écart à la correction et restitue à la suppression', function () {
    $record = collectEggs($this->flock, 900, 20);
    expect((float) eggStock($this->farm)->quantity)->toBe(880.0);

    $record->update(['eggs_collected' => 950]);
    expect((float) eggStock($this->farm)->quantity)->toBe(930.0);

    $record->update(['eggs_broken' => 50]);
    expect((float) eggStock($this->farm)->quantity)->toBe(900.0);

    $record->delete();
    expect((float) eggStock($this->farm)->quantity)->toBe(0.0);
});

it('sort les œufs du stock à la vente et refuse une vente au-delà du disponible', function () {
    collectEggs($this->flock, 880);

    $sale = $this->service->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString()],
        [['product_id' => $this->trays->id, 'product_name' => $this->trays->name, 'unit' => 'plateau', 'quantity' => 20, 'unit_price' => 3000]],
    );

    expect((float) eggStock($this->farm)->quantity)->toBe(280.0)
        ->and(eggStock($this->farm)->movements()->where('reference', $sale->invoice_number)->count())->toBe(1);

    expect(fn () => $this->service->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString()],
        [['product_id' => $this->trays->id, 'product_name' => $this->trays->name, 'unit' => 'plateau', 'quantity' => 10, 'unit_price' => 3000]],
    ))->toThrow(CommercialException::class, 'Stock insuffisant pour « Plateau de 30 œufs » : 10 plateau demandé(s), 9 disponible(s) (280 œuf en stock).');

    expect((float) eggStock($this->farm)->quantity)->toBe(280.0)
        ->and(Sale::query()->where('organisation_id', $this->organisation->id)->count())->toBe(1);
});

it('ajuste le stock quand une ligne est modifiée et le restitue à l\'annulation', function () {
    collectEggs($this->flock, 900);
    $sale = $this->service->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString()],
        [['product_id' => $this->trays->id, 'product_name' => $this->trays->name, 'unit' => 'plateau', 'quantity' => 20, 'unit_price' => 3000]],
    );
    expect((float) eggStock($this->farm)->quantity)->toBe(300.0);

    $line = $sale->items()->first();
    $this->service->syncSaleItems($sale, [['id' => $line->id, 'product_id' => $this->trays->id, 'product_name' => $this->trays->name, 'unit' => 'plateau', 'quantity' => 15, 'unit_price' => 3000]]);
    expect((float) eggStock($this->farm)->quantity)->toBe(450.0);

    $this->service->cancelSale($sale->fresh());
    expect((float) eggStock($this->farm)->quantity)->toBe(900.0);
});

it('un produit sans stock suivi se vend sans contrôle, comme avant', function () {
    $dressed = Product::query()->where('organisation_id', $this->organisation->id)->where('code', 'POUL-ABAT')->firstOrFail();

    $sale = $this->service->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString()],
        [['product_id' => $dressed->id, 'product_name' => $dressed->name, 'unit' => 'kg', 'quantity' => 40, 'unit_price' => 2500]],
    );

    expect($sale->items)->toHaveCount(1)
        ->and((float) eggStock($this->farm)->quantity)->toBe(0.0);
});

it('reprend l\'historique des relevés et des ventes d\'une ferme qui n\'avait pas encore de stock d\'œufs', function () {
    collectEggs($this->flock, 1200, 30);
    $this->service->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString()],
        [['product_id' => $this->trays->id, 'product_name' => $this->trays->name, 'unit' => 'plateau', 'quantity' => 10, 'unit_price' => 3000]],
    );

    // Le stock disparaît (ferme d'avant le suivi) : le prochain besoin le recrée depuis l'historique
    Stock::query()->where('farm_id', $this->farm->id)->where('kind', Stock::KIND_EGGS)->delete();

    $stock = eggStock($this->farm);

    expect((float) $stock->quantity)->toBe(870.0)
        ->and($stock->movements()->where('reference', 'Reprise')->count())->toBe(1)
        ->and($stock->movements()->count())->toBe(1);
});
