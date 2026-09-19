<?php

use App\Enums\CreditNoteStatus;
use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use App\Enums\TransactionCategory;
use App\Exceptions\CommercialException;
use App\Models\Account;
use App\Models\CreditNote;
use App\Models\Customer;
use App\Models\Organisation;
use App\Services\SaleService;

/**
 * Un avoir réduit la créance quand la facture est due, et rembourse réellement
 * (débit de trésorerie) quand la facture est déjà réglée.
 */
beforeEach(function () {
    $this->organisation = Organisation::factory()->create();
    $this->customer = Customer::factory()->create(['organisation_id' => $this->organisation->id]);
    $this->account = Account::factory()->create([
        'organisation_id' => $this->organisation->id,
        'initial_balance' => 50000,
        'current_balance' => 50000,
        'is_active' => true,
    ]);

    $this->sale = app(SaleService::class)->createSale(
        [
            'organisation_id' => $this->organisation->id,
            'customer_id' => $this->customer->id,
            'account_id' => $this->account->id,
            'sale_date' => now()->toDateString(),
        ],
        [['product_name' => 'Poulets', 'unit' => 'sujet', 'quantity' => 4, 'unit_price' => 2500]],
    );
});

function issueCreditNote($sale, float $amount): CreditNote
{
    return CreditNote::create([
        'sale_id' => $sale->id,
        'issue_date' => now()->toDateString(),
        'amount' => $amount,
        'reason' => 'Sujets non conformes',
        'status' => CreditNoteStatus::Issued,
    ]);
}

it('réduit la créance d\'une facture due sans toucher la trésorerie', function () {
    $creditNote = issueCreditNote($this->sale, 3000);

    $creditNote->apply();

    $sale = $this->sale->fresh();

    expect((float) $sale->balance)->toBe(7000.0)
        ->and((float) $sale->paid)->toBe(0.0)
        ->and($sale->status)->toBe(SaleStatus::Pending)
        ->and((float) $creditNote->fresh()->applied_amount)->toBe(3000.0)
        ->and((float) $creditNote->fresh()->refunded_amount)->toBe(0.0)
        ->and($creditNote->fresh()->status)->toBe(CreditNoteStatus::Applied)
        ->and($this->account->transactions()->count())->toBe(0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(50000.0)
        ->and((float) $this->customer->fresh()->balance)->toBe(7000.0)
        ->and($sale->getNetTotal())->toBe(7000.0);
});

it('rembourse réellement un avoir sur une facture déjà réglée', function () {
    $this->sale->addPayment(10000, PaymentMethod::Cash);
    expect((float) $this->account->fresh()->current_balance)->toBe(60000.0);

    $creditNote = issueCreditNote($this->sale, 2500);
    $creditNote->apply();

    $sale = $this->sale->fresh();
    $refund = $this->account->transactions()->where('category', TransactionCategory::Refund->value)->first();

    expect((float) $sale->balance)->toBe(0.0)
        ->and($sale->status)->toBe(SaleStatus::Paid)
        ->and((float) $creditNote->fresh()->applied_amount)->toBe(0.0)
        ->and((float) $creditNote->fresh()->refunded_amount)->toBe(2500.0)
        ->and($creditNote->fresh()->refund_account_id)->toBe($this->account->id)
        ->and($refund)->not->toBeNull()
        ->and((float) $refund->amount)->toBe(2500.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(57500.0)
        ->and($sale->getNetTotal())->toBe(7500.0);
});

it('combine réduction de créance et remboursement quand l\'avoir dépasse le solde dû', function () {
    $this->sale->addPayment(6000, PaymentMethod::Cash);

    $creditNote = issueCreditNote($this->sale, 5000);
    $creditNote->apply();

    $sale = $this->sale->fresh();

    expect((float) $creditNote->fresh()->applied_amount)->toBe(4000.0)
        ->and((float) $creditNote->fresh()->refunded_amount)->toBe(1000.0)
        ->and((float) $sale->balance)->toBe(0.0)
        ->and((float) $sale->paid)->toBe(6000.0)
        ->and($sale->status)->toBe(SaleStatus::Paid)
        ->and((float) $this->account->fresh()->current_balance)->toBe(55000.0)
        ->and((float) $this->customer->fresh()->balance)->toBe(0.0);
});

it('exige un compte de remboursement quand la facture réglée n\'en a pas', function () {
    $sale = app(SaleService::class)->createSale(
        ['organisation_id' => $this->organisation->id, 'sale_date' => now()->toDateString()],
        [['product_name' => 'Œufs', 'unit' => 'plateau', 'quantity' => 2, 'unit_price' => 3000]],
    );
    $sale->addPayment(6000, PaymentMethod::Cash);

    $creditNote = issueCreditNote($sale, 1000);

    expect(fn () => $creditNote->apply())->toThrow(CommercialException::class)
        ->and($creditNote->fresh()->status)->toBe(CreditNoteStatus::Issued);

    // Avec un compte explicitement choisi, le remboursement passe
    $creditNote->apply($this->account->id);

    expect((float) $creditNote->fresh()->refunded_amount)->toBe(1000.0)
        ->and((float) $this->account->fresh()->current_balance)->toBe(49000.0);
});

it('l\'annulation d\'une facture remet son solde et l\'encours client à zéro', function () {
    expect((float) $this->customer->fresh()->balance)->toBe(10000.0);

    app(SaleService::class)->cancelSale($this->sale);

    expect((float) $this->sale->fresh()->balance)->toBe(0.0)
        ->and((float) $this->customer->fresh()->balance)->toBe(0.0);
});
