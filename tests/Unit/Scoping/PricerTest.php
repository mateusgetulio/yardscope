<?php

use App\Scoping\Data\JobScope;
use App\Scoping\Data\PropertyProfile;
use App\Scoping\Data\RateCard;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Exceptions\InvalidRateCard;
use App\Scoping\ScopeBuilder;

it('prices the worked example at $165 for 2.0 to 3.5 hours, leaving the branch out', function () {
    $estimate = pricer()->estimate(workedScope());

    expect($estimate)->not->toBeNull()
        ->and($estimate->hours->low)->toBe(2.3)
        ->and($estimate->hours->high)->toBe(3.225)
        ->and($estimate->shownLowHours)->toBe(2.0)
        ->and($estimate->shownHighHours)->toBe(3.5)
        ->and($estimate->priceCents)->toBe(16500)
        ->and(array_map(fn ($line) => $line->lineId, $estimate->lines))->toBe(['line-1', 'line-2'])
        ->and($estimate->lines[0]->laborCents)->toBe(8460)
        ->and($estimate->lines[1]->laborCents)->toBe(4800);
});

it('returns no estimate when nothing is priceable', function () {
    expect(pricer()->estimate(JobScope::unreadable(workedProfile(), 'unreadable')))->toBeNull();
});

it('scales cleanup hours by the section size from the property profile', function () {
    $small = (new ScopeBuilder)->build(workedObservation(), PropertyProfile::fromArray(['backyard' => 'small', 'side_yard' => 'small', 'front_yard' => 'small']));
    $large = (new ScopeBuilder)->build(workedObservation(), PropertyProfile::fromArray(['backyard' => 'large', 'side_yard' => 'small', 'front_yard' => 'small']));

    expect(pricer()->estimate($small)?->lines[0]->hours->high)->toBe(1.35)
        ->and(pricer()->estimate($large)?->lines[0]->hours->high)->toBe(2.97);
});

it('rounds the price up to the configured step', function () {
    expect(pricer(['price_rounding_cents' => 100])->estimate(workedScope())?->priceCents)->toBe(16200)
        ->and(pricer(['price_rounding_cents' => 1000])->estimate(workedScope())?->priceCents)->toBe(17000);
});

it('never prices a line the gate did not mark priceable', function () {
    $scope = workedScope();
    $lines = array_map(fn ($line) => new ScopeLine(
        id: $line->id, type: $line->type, section: $line->section, observed: $line->observed, current: $line->current,
        disposition: LineDisposition::NeedsPhotos, checks: $line->checks, photoRequest: null, note: null,
        countingEvidence: $line->countingEvidence, supportingEvidence: $line->supportingEvidence, evidence: $line->evidence,
        uncertain: $line->uncertain, requested: $line->requested,
    ), $scope->lines);

    expect(pricer()->estimate($scope->withLines($lines)))->toBeNull();
});

it('rejects an invalid rate card', function (array $overrides, string $message) {
    expect(fn () => RateCard::fromArray(array_replace_recursive(shippedRates(), $overrides)))->toThrow(InvalidRateCard::class, $message);
})->with([
    'negative visit fee' => [['visit_fee_cents' => -1], 'visit_fee_cents cannot be negative.'],
    'zero hourly rate' => [['hourly_rate_cents' => 0], 'hourly_rate_cents must be positive.'],
    'high below low' => [['hours' => ['shrub_trimming' => ['small' => [0.5, 0.1]]]], 'hours for shrub_trimming small must be a low and a high, low first.'],
    'range not two numbers' => [['hours' => ['shrub_trimming' => ['small' => 'wide']]], 'hours for shrub_trimming small must be a list of two numbers.'],
    'missing section scale' => [['section_scale' => ['medium' => 0]], 'section_scale needs a positive medium factor.'],
    'fee given as text' => [['visit_fee_cents' => '29'], 'visit_fee_cents must be an integer.'],
]);

it('rejects a rate card missing a service entirely', function () {
    $rates = shippedRates();
    unset($rates['hours']['bed_weeding']);

    expect(fn () => RateCard::fromArray($rates))->toThrow(InvalidRateCard::class, 'hours needs at least one range for bed_weeding.');
});
