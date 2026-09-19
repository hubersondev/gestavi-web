<?php

use App\Enums\CreditNoteStatus;
use App\Models\CreditNote;

test('credit note status helpers detect current state', function () {
    $draft = new CreditNote(['status' => CreditNoteStatus::Draft]);
    $issued = new CreditNote(['status' => CreditNoteStatus::Issued]);
    $applied = new CreditNote(['status' => CreditNoteStatus::Applied]);
    $cancelled = new CreditNote(['status' => CreditNoteStatus::Cancelled]);

    expect($draft->isDraft())->toBeTrue()
        ->and($issued->isIssued())->toBeTrue()
        ->and($applied->isApplied())->toBeTrue()
        ->and($cancelled->isCancelled())->toBeTrue();
});

test('credit note status draft is not issued nor applied', function () {
    $draft = new CreditNote(['status' => CreditNoteStatus::Draft]);

    expect($draft->isIssued())->toBeFalse()
        ->and($draft->isApplied())->toBeFalse()
        ->and($draft->isCancelled())->toBeFalse();
});
