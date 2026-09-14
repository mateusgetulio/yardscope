<?php

use App\Scoping\CorrectionApplier;
use App\Scoping\Data\Correction;
use App\Scoping\Data\Observation;
use App\Scoping\Enums\CorrectionField;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;
use App\Scoping\Enums\Size;
use App\Scoping\Enums\ValueOrigin;
use App\Scoping\Exceptions\InvalidCorrection;
use App\Scoping\ScopeBuilder;

it('lets the customer raise a count the photos could not show, with provenance', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', CorrectionField::Quantity, '4', '6', 'two behind the shed'));
    $line = $scope->line('line-2');

    expect($line?->current->quantity)->toBe(6)
        ->and($line?->observed->quantity)->toBe(4)
        ->and($line?->origin())->toBe(ValueOrigin::CustomerCorrected)
        ->and($line?->lastCorrection()?->reason)->toBe('two behind the shed')
        ->and($line?->lastCorrection()?->modelValue)->toBe('4')
        ->and($line?->disposition)->toBe(LineDisposition::Priceable)
        ->and(pricer()->estimate($scope)?->priceCents)->toBe(19000);
});

it('moves a shrub corrected to large out of the price and to a pro', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', CorrectionField::Size, 'medium', 'large', null));

    expect($scope->line('line-2')?->disposition)->toBe(LineDisposition::ManualQuote)
        ->and($scope->line('line-2')?->current->size)->toBe(Size::Large)
        ->and($scope->readiness)->toBe(RequestReadiness::Partial)
        ->and(pricer()->estimate($scope)?->priceCents)->toBe(11500);
});

it('never raises the price when a line is removed', function () {
    $before = pricer()->estimate(workedScope())?->priceCents;
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', CorrectionField::Removed, '4 medium shrubs', '', 'we will do these ourselves'));

    expect($scope->line('line-2')?->disposition)->toBe(LineDisposition::Rejected)
        ->and($scope->line('line-2')?->note)->toBe('Removed by the customer.')
        ->and(pricer()->estimate($scope)?->priceCents)->toBeLessThan($before);
});

it('adds a suggested line through the gate', function () {
    $observation = Observation::fromArray(array_replace(workedExample()['observation'], ['requested_in_sentence' => ['yard_cleanup']]));
    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    $added = (new CorrectionApplier)->apply($scope, new Correction('line-2', CorrectionField::Added, '', '', null));
    $branch = (new CorrectionApplier)->apply($scope, new Correction('line-3', CorrectionField::Added, '', '', null));

    expect($added->line('line-2')?->disposition)->toBe(LineDisposition::Priceable)
        ->and(pricer()->estimate($added)?->priceCents)->toBe(16500)
        ->and($branch->line('line-3')?->disposition)->toBe(LineDisposition::ManualQuote);
});

it('keeps the photo rules when re-gating a corrected line', function () {
    $observation = Observation::fromArray(array_replace(workedExample()['observation'], [
        'photos' => [['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true], ['photo' => 2, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true]],
        'access' => ['narrow_gate_possible' => false, 'evidence' => []],
    ]));
    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    $corrected = (new CorrectionApplier)->apply($scope, new Correction('line-2', CorrectionField::Quantity, '4', '5', null));

    expect($corrected->line('line-2')?->disposition)->toBe(LineDisposition::NeedsPhotos)
        ->and($corrected->line('line-2')?->photoRequest?->message)->toBe('One photo showing the whole backyard, taken from the far end.');
});

it('gives an added suggestion in an uncovered section its photo request', function () {
    $observation = Observation::fromArray(array_replace(workedExample()['observation'], [
        'photos' => [['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true], ['photo' => 2, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true]],
        'access' => ['narrow_gate_possible' => false, 'evidence' => []],
        'requested_in_sentence' => ['yard_cleanup'],
    ]));
    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    $added = (new CorrectionApplier)->apply($scope, new Correction('line-2', CorrectionField::Added, '', '', null));

    expect($added->line('line-2')?->disposition)->toBe(LineDisposition::NeedsPhotos)
        ->and($added->line('line-2')?->photoRequest?->message)->toBe('One photo showing the whole backyard, taken from the far end.');
});

it('brings a large shrub back into the price when corrected down to medium', function () {
    $observation = Observation::fromArray(array_replace(workedExample()['observation'], [
        'requested_in_sentence' => ['shrub_trimming'],
        'service_lines' => [['type' => 'shrub_trimming', 'section' => 'backyard', 'quantity' => 2, 'size' => 'large', 'counting_evidence' => ['photo' => 1, 'note' => 'two']]],
    ]));
    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    $corrected = (new CorrectionApplier)->apply($scope, new Correction('line-1', CorrectionField::Size, 'large', 'medium', 'they are waist high'));

    expect($scope->readiness)->toBe(RequestReadiness::ManualQuote)
        ->and($corrected->line('line-1')?->disposition)->toBe(LineDisposition::Priceable)
        ->and($corrected->readiness)->toBe(RequestReadiness::Ready)
        ->and(pricer()->estimate($corrected)?->priceCents)->toBe(5500);
});

it('lets a removed line come back through the gate', function () {
    $applier = new CorrectionApplier;
    $removed = $applier->apply(workedScope(), new Correction('line-2', CorrectionField::Removed, '', '', null));

    $back = $applier->apply($removed, new Correction('line-2', CorrectionField::Added, '', '', null));

    expect($back->line('line-2')?->disposition)->toBe(LineDisposition::Priceable)
        ->and($back->line('line-2')?->corrections)->toHaveCount(2)
        ->and($back->line('line-2')?->origin())->toBe(ValueOrigin::AiObserved)
        ->and(pricer()->estimate($back)?->priceCents)->toBe(16500);
});

it('keeps every correction on the line, in order', function () {
    $applier = new CorrectionApplier;
    $scope = $applier->apply(workedScope(), new Correction('line-2', CorrectionField::Quantity, '', '6', 'two behind the shed'));
    $scope = $applier->apply($scope, new Correction('line-2', CorrectionField::Size, '', 'small', null));

    $corrections = $scope->line('line-2')?->corrections ?? [];

    expect(array_map(fn ($c) => [$c->field->value, $c->modelValue, $c->customerValue], $corrections))
        ->toBe([['quantity', '4', '6'], ['size', 'medium', 'small']]);
});

it('rejects corrections outside the domain', function (Correction $correction, string $message) {
    expect(fn () => (new CorrectionApplier)->apply(workedScope(), $correction))->toThrow(InvalidCorrection::class, $message);
})->with([
    'unknown line' => [new Correction('line-9', CorrectionField::Quantity, '4', '5', null), 'There is no line [line-9] to correct.'],
    'count of zero' => [new Correction('line-2', CorrectionField::Quantity, '4', '0', null), 'A Shrub trimming count must be between 1 and 20.'],
    'count too high' => [new Correction('line-2', CorrectionField::Quantity, '4', '21', null), 'A Shrub trimming count must be between 1 and 20.'],
    'count as text' => [new Correction('line-2', CorrectionField::Quantity, '4', 'six', null), 'A Shrub trimming count must be between 1 and 20.'],
    'size on a cleanup' => [new Correction('line-1', CorrectionField::Size, '', 'large', null), 'Yard cleanup takes a severity of light, moderate or heavy.'],
    'stale model value' => [new Correction('line-2', CorrectionField::Quantity, '3', '5', null), 'Line [line-2] currently has quantity [4], not [3].'],
    'severity on shrubs' => [new Correction('line-2', CorrectionField::Severity, '', 'heavy', null), 'Shrub trimming takes a size of small, medium or large.'],
    'unknown size' => [new Correction('line-2', CorrectionField::Size, 'medium', 'huge', null), 'Shrub trimming takes a size of small, medium or large.'],
    'adding a line that was not suggested' => [new Correction('line-1', CorrectionField::Added, '', '', null), 'Line [line-1] was not a suggestion.'],
]);

it('refuses to correct a placeholder that no photo shows yet', function () {
    $observation = Observation::fromArray(array_replace(workedExample()['observation'], ['requested_in_sentence' => ['yard_cleanup', 'bed_weeding']]));
    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect(fn () => (new CorrectionApplier)->apply($scope, new Correction('line-4', CorrectionField::Quantity, '', '2', null)))
        ->toThrow(InvalidCorrection::class, 'Line [line-4] has nothing to correct until a photo shows it.');
});

it('refuses to correct a removed line', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', CorrectionField::Removed, '', '', null));

    expect(fn () => (new CorrectionApplier)->apply($scope, new Correction('line-2', CorrectionField::Quantity, '4', '5', null)))
        ->toThrow(InvalidCorrection::class, 'Line [line-2] has to be added to the job before it can be corrected.');
});
