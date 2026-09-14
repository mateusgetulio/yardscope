<?php

use App\Scoping\CorrectionApplier;
use App\Scoping\Data\Correction;
use App\Scoping\Enums\CorrectionField;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\ValueOrigin;
use App\Scoping\ProBriefBuilder;

it('copies every line with its origin, the observed value and the customer reason', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', CorrectionField::Quantity, '4', '6', 'Two more behind the shed'));

    $brief = (new ProBriefBuilder)->build($scope, pricer()->estimate($scope));

    expect($brief->lines)->toHaveCount(3)
        ->and($brief->lines[0]->origin)->toBe(ValueOrigin::AiObserved)
        ->and($brief->lines[0]->observed)->toBeNull()
        ->and($brief->lines[1]->origin)->toBe(ValueOrigin::CustomerCorrected)
        ->and($brief->lines[1]->values->quantity)->toBe(6)
        ->and($brief->lines[1]->observed?->quantity)->toBe(4)
        ->and($brief->lines[1]->customerReason)->toBe('Two more behind the shed')
        ->and($brief->lines[2]->disposition)->toBe(LineDisposition::ManualQuote)
        ->and($brief->lines[2]->note)->toBe('Large branches are quoted on site.')
        ->and($brief->estimate?->priceCents)->toBeGreaterThan(16500);
});

it('groups evidence notes by photo and lists what is still open', function () {
    $brief = (new ProBriefBuilder)->build(workedScope(), null);

    expect(array_keys($brief->photoNotes))->toBe([1, 2, 3])
        ->and($brief->photoNotes[1])->toContain('Yard cleanup: leaves and debris along fence line and patio')
        ->and($brief->photoNotes[1])->toContain('Shrub trimming: wide view shows four distinct shrubs along back fence')
        ->and($brief->photoNotes[3])->toBe(['Access: side gate looks narrower than a mower deck'])
        ->and($brief->accessNotes)->toHaveCount(1)
        ->and($brief->openQuestions)->toBe([
            'Branch removal: branch diameter hard to judge',
            'From the analysis: No photo shows the left side of the backyard.',
        ]);
});

it('keeps a removed line in the brief, marked as removed by the customer', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-3', CorrectionField::Removed, '', '', 'Neighbor already took it'));

    $line = (new ProBriefBuilder)->build($scope, null)->lines[2];

    expect($line->removedByCustomer)->toBeTrue()
        ->and($line->customerReason)->toBe('Neighbor already took it')
        ->and($line->disposition)->toBe(LineDisposition::Rejected);
});
