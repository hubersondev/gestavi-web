<?php

use App\Models\AccountTransfer;
use App\Models\Attendance;
use App\Models\DailyRecord;
use App\Models\FarmLog;
use App\Models\HealthIncident;
use App\Models\HealthProgram;
use App\Models\Leave;
use App\Models\Payment;
use App\Models\Payroll;
use App\Models\ProductionPhase;
use App\Models\PurchaseOrderItem;
use App\Models\QuoteItem;
use App\Models\SaleItem;
use App\Models\StockCategory;
use App\Models\StockInventory;
use App\Models\StockLot;
use App\Models\StockMovement;
use App\Models\Transaction;
use App\Models\Vaccination;
use App\Models\Veterinarian;
use App\Models\VetVisit;

/**
 * Chaque factory doit produire un enregistrement valide (contraintes CHECK, NOT NULL,
 * FK composites organisation/ferme) : filet de sécurité pour les tests métier à venir.
 */
it('chaque factory crée un enregistrement cohérent', function (string $model) {
    $record = $model::factory()->create();

    expect($record->exists)->toBeTrue()
        ->and($model::query()->whereKey($record->getKey())->exists())->toBeTrue();

    if (in_array('organisation_id', $record->getFillable(), true)) {
        expect($record->organisation_id)->not->toBeNull();
    }
})->with([
    DailyRecord::class,
    Vaccination::class,
    HealthIncident::class,
    HealthProgram::class,
    VetVisit::class,
    FarmLog::class,
    ProductionPhase::class,
    Leave::class,
    Attendance::class,
    Payroll::class,
    Transaction::class,
    AccountTransfer::class,
    Payment::class,
    StockLot::class,
    StockMovement::class,
    StockInventory::class,
    StockCategory::class,
    Veterinarian::class,
    SaleItem::class,
    QuoteItem::class,
    PurchaseOrderItem::class,
]);
