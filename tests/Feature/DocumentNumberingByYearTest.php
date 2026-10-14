<?php

use App\Enums\ExpenseCategory;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Farm;
use App\Models\Organisation;
use App\Services\SaleService;
use Database\Seeders\ChartAccountSeeder;

/**
 * Reprise d'un exercice passé : le numéro d'une pièce suit l'année de sa date, pas celle de la saisie.
 */
beforeEach(function () {
    $this->seed(ChartAccountSeeder::class);
    $this->organisation = Organisation::factory()->create();
    $this->farm = Farm::factory()->create(['organisation_id' => $this->organisation->id]);
});

it('numérote factures et dépenses dans l\'année de leur date, avec une séquence propre à chaque année', function () {
    $lastYear = now()->subYear()->year;

    $sale2025 = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => "{$lastYear}-03-15", 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 10, 'unit_price' => 100]],
    );
    $saleNow = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => now()->toDateString(), 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 10, 'unit_price' => 100]],
    );
    $sale2025bis = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'sale_date' => "{$lastYear}-04-01", 'status' => SaleStatus::Pending, 'tax_percent' => 0],
        [['product_name' => 'Œufs', 'quantity' => 10, 'unit_price' => 100]],
    );

    expect($sale2025->invoice_number)->toStartWith("FAC-{$lastYear}-")
        ->and($saleNow->invoice_number)->toStartWith('FAC-'.now()->year.'-')
        ->and($sale2025bis->invoice_number)->toBe(str_replace('0001', '0002', $sale2025->invoice_number));

    $expense = Expense::factory()->create(['organisation_id' => $this->organisation->id, 'farm_id' => $this->farm->id, 'category' => ExpenseCategory::Utilities, 'amount' => 1000, 'balance' => 1000, 'expense_date' => "{$lastYear}-06-30"]);

    expect($expense->expense_number)->toStartWith("DEP-{$lastYear}-");
});
