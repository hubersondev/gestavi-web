<?php

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Organisation;
use App\Models\Sale;
use App\Models\Sequence;
use App\Services\NumberSequence;

/**
 * Numérotation continue, par périmètre et par année, reprenant l'existant.
 */
it('délivre des numéros consécutifs par organisation et par clé', function () {
    $sequence = app(NumberSequence::class);

    expect($sequence->next('invoice', 1, 2026))->toBe(1)
        ->and($sequence->next('invoice', 1, 2026))->toBe(2)
        ->and($sequence->next('invoice', 2, 2026))->toBe(1)
        ->and($sequence->next('quote', 1, 2026))->toBe(1)
        ->and($sequence->next('invoice', 1, 2027))->toBe(1);
});

it('formate avec ou sans année', function () {
    $sequence = app(NumberSequence::class);

    expect($sequence->nextFormatted('FAC-', 'invoice', 1, 2026))->toBe('FAC-2026-00001')
        ->and($sequence->nextFormatted('CLI-', 'customer', 1))->toBe('CLI-00001')
        ->and($sequence->nextFormatted('CTR-', 'contract', 7, 2026, 3, NumberSequence::SCOPE_EMPLOYEE))->toBe('CTR-2026-001');
});

it('reprend la numérotation après le dernier numéro existant en base', function () {
    $organisation = Organisation::factory()->create();

    Sale::factory()->create(['organisation_id' => $organisation->id, 'invoice_number' => 'FAC-'.now()->year.'-00041']);
    Sale::factory()->create(['organisation_id' => $organisation->id, 'invoice_number' => 'FAC-'.now()->year.'-00007']);

    expect(Sale::generateInvoiceNumber($organisation->id))->toBe('FAC-'.now()->year.'-00042')
        ->and(Sale::generateInvoiceNumber($organisation->id))->toBe('FAC-'.now()->year.'-00043');
});

it('ne régresse pas après une suppression', function () {
    $organisation = Organisation::factory()->create();

    $first = Customer::factory()->create(['organisation_id' => $organisation->id]);
    $second = Customer::factory()->create(['organisation_id' => $organisation->id]);

    expect($first->code)->toBe('CLI-00001')
        ->and($second->code)->toBe('CLI-00002');

    $second->forceDelete();

    $third = Customer::factory()->create(['organisation_id' => $organisation->id]);

    expect($third->code)->toBe('CLI-00003');
});

it('numérote les dépenses par année et par organisation', function () {
    $organisationA = Organisation::factory()->create();
    $organisationB = Organisation::factory()->create();

    $a1 = Expense::factory()->create(['organisation_id' => $organisationA->id]);
    $a2 = Expense::factory()->create(['organisation_id' => $organisationA->id]);
    $b1 = Expense::factory()->create(['organisation_id' => $organisationB->id]);

    $year = now()->year;

    expect($a1->expense_number)->toBe("DEP-{$year}-00001")
        ->and($a2->expense_number)->toBe("DEP-{$year}-00002")
        ->and($b1->expense_number)->toBe("DEP-{$year}-00001")
        ->and(Sequence::query()->where('key', 'expense')->count())->toBe(2);
});
