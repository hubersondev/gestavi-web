<?php

use App\Enums\PaymentMethod;
use App\Enums\TransactionCategory;
use App\Exceptions\CommercialException;
use App\Exceptions\StockException;
use App\Models\Account;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\Stock;

/**
 * Les soldes et quantités sont relus en base sous verrou : une instance
 * « périmée » en mémoire ne peut pas écraser un mouvement concurrent.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
});

it('un crédit calcule le solde depuis la base, pas depuis l\'instance en mémoire', function () {
    $account = Account::factory()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 1000,
        'current_balance' => 1000,
        'is_active' => true,
    ]);

    // Deux caissiers chargent le même compte
    $instanceA = Account::find($account->id);
    $instanceB = Account::find($account->id);

    $instanceA->credit(500, 'Encaissement A');
    $transactionB = $instanceB->credit(300, 'Encaissement B');

    expect((float) $account->fresh()->current_balance)->toBe(1800.0)
        ->and((float) $transactionB->balance_before)->toBe(1500.0)
        ->and((float) $transactionB->balance_after)->toBe(1800.0)
        ->and((float) $instanceB->current_balance)->toBe(1800.0);
});

it('une écriture porte la date comptable fournie', function () {
    $account = Account::factory()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $transaction = $account->credit(
        amount: 100,
        description: 'Rétro-saisie',
        category: TransactionCategory::Payment,
        date: '2026-08-28',
    );

    expect($transaction->transaction_date->toDateString())->toBe('2026-08-28');
});

it('refuse un montant nul ou négatif', function () {
    $account = Account::factory()->create(['organisation_id' => $this->organisation->id]);

    expect(fn () => $account->debit(0, 'Rien'))->toThrow(InvalidArgumentException::class);
});

it('un paiement vérifie le solde dû de la facture en base et propage sa date au compte', function () {
    $account = Account::factory()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 0,
        'current_balance' => 0,
        'is_active' => true,
    ]);

    $sale = Sale::factory()->create([
        'organisation_id' => $this->organisation->id,
        'account_id' => $account->id,
    ]);
    $sale->items()->create(['product_name' => 'Œufs', 'quantity' => 10, 'unit_price' => 1000, 'sort_order' => 0]);
    $sale->refresh()->calculateTotals();

    $stale = Sale::find($sale->id);

    $sale->addPayment(6000, PaymentMethod::Cash, paidAt: now()->subDays(3));

    // L'instance périmée croit encore devoir 10 000 : le verrou relit 4 000 en base
    expect(fn () => $stale->addPayment(6000, PaymentMethod::Cash))->toThrow(CommercialException::class);

    $stale->addPayment(4000, PaymentMethod::Cash);

    $sale->refresh();

    expect((float) $sale->paid)->toBe(10000.0)
        ->and((float) $sale->balance)->toBe(0.0)
        ->and($account->transactions()->orderBy('id')->first()->transaction_date->toDateString())
        ->toBe(now()->subDays(3)->toDateString());
});

it('une sortie de stock vérifie la disponibilité sur la ligne verrouillée', function () {
    $stock = Stock::factory()->create([
        'organisation_id' => $this->organisation->id,
        'quantity' => 100,
    ]);

    $instanceA = Stock::find($stock->id);
    $instanceB = Stock::find($stock->id);

    $instanceA->recordOut(80);

    // L'instance B croit encore disposer de 100 unités
    expect(fn () => $instanceB->recordOut(50))->toThrow(StockException::class);

    $instanceB->recordOut(20);

    expect((float) $stock->fresh()->quantity)->toBe(0.0)
        ->and((float) $instanceB->quantity)->toBe(0.0);
});
