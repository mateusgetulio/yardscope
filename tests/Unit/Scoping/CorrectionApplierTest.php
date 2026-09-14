<?php

use App\Scoping\CorrectionApplier;
use App\Scoping\Data\Correction;
use App\Scoping\Data\Observation;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;
use App\Scoping\Enums\Size;
use App\Scoping\Enums\ValueOrigin;
use App\Scoping\Exceptions\InvalidCorrection;
use App\Scoping\ScopeBuilder;

it('lets the customer raise a count the photos could not show, with provenance', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', 'quantity', '4', '6', 'two behind the shed'));
    $line = $scope->line('line-2');

    expect($line?->current->quantity)->toBe(6)
        ->and($line?->observed->quantity)->toBe(4)
        ->and($line?->origin())->toBe(ValueOrigin::CustomerCorrected)
        ->and($line?->correction?->reason)->toBe('two behind the shed')
        ->and($line?->disposition)->toBe(LineDisposition::Priceable)
        ->and(pricer()->estimate($scope)?->priceCents)->toBe(19000);
});

it('moves a shrub corrected to large out of the price and to a pro', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', 'size', 'medium', 'large', null));

    expect($scope->line('line-2')?->disposition)->toBe(LineDisposition::ManualQuote)
        ->and($scope->line('line-2')?->current->size)->toBe(Size::Large)
        ->and($scope->readiness)->toBe(RequestReadiness::Partial)
        ->and(pricer()->estimate($scope)?->priceCents)->toBe(11500);
});

it('never raises the price when a line is removed', function () {
    $before = pricer()->estimate(workedScope())?->priceCents;
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', 'removed', '4 medium shrubs', '', 'we will do these ourselves'));

    expect($scope->line('line-2')?->disposition)->toBe(LineDisposition::Rejected)
        ->and($scope->line('line-2')?->note)->toBe('Removed by the customer.')
        ->and(pricer()->estimate($scope)?->priceCents)->toBeLessThan($before);
});

it('adds a suggested line through the gate', function () {
    $observation = Observation::fromArray(array_replace(workedExample()['observation'], ['requested_in_sentence' => ['yard_cleanup']]));
    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    $added = (new CorrectionApplier)->apply($scope, new Correction('line-2', 'added', '', '', null));
    $branch = (new CorrectionApplier)->apply($scope, new Correction('line-3', 'added', '', '', null));

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

    $corrected = (new CorrectionApplier)->apply($scope, new Correction('line-2', 'quantity', '4', '5', null));

    expect($corrected->line('line-2')?->disposition)->toBe(LineDisposition::NeedsPhotos);
});

it('rejects corrections outside the domain', function (Correction $correction, string $message) {
    expect(fn () => (new CorrectionApplier)->apply(workedScope(), $correction))->toThrow(InvalidCorrection::class, $message);
})->with([
    'unknown line' => [new Correction('line-9', 'quantity', '4', '5', null), 'There is no line [line-9] to correct.'],
    'unknown field' => [new Correction('line-2', 'colour', '', 'green', null), 'Unknown correction field [colour].'],
    'count of zero' => [new Correction('line-2', 'quantity', '4', '0', null), 'A Shrub trimming count must be between 1 and 20.'],
    'count too high' => [new Correction('line-2', 'quantity', '4', '21', null), 'A Shrub trimming count must be between 1 and 20.'],
    'count as text' => [new Correction('line-2', 'quantity', '4', 'six', null), 'A Shrub trimming count must be between 1 and 20.'],
    'size on a cleanup' => [new Correction('line-1', 'size', '', 'large', null), 'Yard cleanup takes a severity of light, moderate or heavy.'],
    'severity on shrubs' => [new Correction('line-2', 'severity', '', 'heavy', null), 'Shrub trimming takes a size of small, medium or large.'],
    'unknown size' => [new Correction('line-2', 'size', 'medium', 'huge', null), 'Shrub trimming takes a size of small, medium or large.'],
    'adding a line that was not suggested' => [new Correction('line-1', 'added', '', '', null), 'Line [line-1] was not a suggestion.'],
]);

it('refuses to correct a placeholder that no photo shows yet', function () {
    $observation = Observation::fromArray(array_replace(workedExample()['observation'], ['requested_in_sentence' => ['yard_cleanup', 'bed_weeding']]));
    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect(fn () => (new CorrectionApplier)->apply($scope, new Correction('line-4', 'quantity', '', '2', null)))
        ->toThrow(InvalidCorrection::class, 'Line [line-4] has nothing to correct until a photo shows it.');
});

it('refuses to correct a removed line', function () {
    $scope = (new CorrectionApplier)->apply(workedScope(), new Correction('line-2', 'removed', '', '', null));

    expect(fn () => (new CorrectionApplier)->apply($scope, new Correction('line-2', 'quantity', '4', '5', null)))
        ->toThrow(InvalidCorrection::class, 'Line [line-2] has to be added to the job before it can be corrected.');
});
