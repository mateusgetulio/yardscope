<?php

use App\Scoping\Data\JobScope;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\Observation;
use App\Scoping\Data\ReadinessRollup;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\ReadinessRule;
use App\Scoping\Enums\RequestReadiness;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\ScopeBuilder;

function observationWith(array $overrides): Observation
{
    if (isset($overrides['photos']) && ! isset($overrides['access'])) {
        $overrides['access'] = ['narrow_gate_possible' => false, 'evidence' => []];
    }

    return Observation::fromArray(array_replace(workedExample()['observation'], $overrides));
}

it('prices the worked example partially, with the large branch left to a pro', function () {
    $scope = (new ScopeBuilder)->build(workedObservation(), workedProfile());

    expect($scope->readiness)->toBe(RequestReadiness::Partial)
        ->and(array_map(fn ($line) => $line->disposition, $scope->lines))->toBe([LineDisposition::Priceable, LineDisposition::Priceable, LineDisposition::ManualQuote])
        ->and($scope->lines[2]->note)->toBe('Large branches are quoted on site.')
        ->and($scope->lines[2]->section)->toBe(Section::Backyard)
        ->and($scope->lines[0]->passedChecks())->toBe(3);
});

it('asks for a wide photo when only a close-up shows the section', function () {
    $observation = observationWith(['photos' => [
        ['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
        ['photo' => 2, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
        ['photo' => 3, 'view' => 'medium', 'sections' => ['side_yard'], 'usable' => true],
    ]]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect($scope->readiness)->toBe(RequestReadiness::NeedsPhotos)
        ->and($scope->lines[0]->disposition)->toBe(LineDisposition::NeedsPhotos)
        ->and($scope->lines[0]->photoRequest?->message)->toBe('One photo showing the whole backyard, taken from the far end.')
        ->and($scope->lines[0]->passedChecks())->toBe(2);
});

it('becomes ready to quote the cleanup once the requested photo arrives', function () {
    $before = observationWith(['photos' => [
        ['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
        ['photo' => 2, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
    ]]);
    $after = observationWith(['photos' => [
        ['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
        ['photo' => 2, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
        ['photo' => 3, 'view' => 'wide', 'sections' => ['backyard'], 'usable' => true],
    ]]);

    $builder = new ScopeBuilder;

    expect($builder->build($before, workedProfile())->readiness)->toBe(RequestReadiness::NeedsPhotos)
        ->and($builder->build($after, workedProfile())->readiness)->toBe(RequestReadiness::Partial);
});

it('asks for photos of every line when fewer than two photos are usable', function () {
    $observation = observationWith(['photos' => [
        ['photo' => 1, 'view' => 'wide', 'sections' => ['backyard'], 'usable' => true],
        ['photo' => 2, 'view' => 'wide', 'sections' => ['backyard'], 'usable' => false],
    ]]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect($scope->lines[0]->disposition)->toBe(LineDisposition::NeedsPhotos)
        ->and($scope->lines[0]->photoRequest?->message)->toBe('Take one wide photo of the backyard.')
        ->and($scope->lines[1]->disposition)->toBe(LineDisposition::NeedsPhotos);
});

it('sends a large shrub to a pro even when its section has no wide view (R5 before R2)', function () {
    $observation = observationWith([
        'photos' => [
            ['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
            ['photo' => 2, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
        ],
        'requested_in_sentence' => ['shrub_trimming'],
        'service_lines' => [['type' => 'shrub_trimming', 'section' => 'backyard', 'quantity' => 2, 'size' => 'large', 'counting_evidence' => ['photo' => 1, 'note' => 'two large shrubs']]],
    ]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect($scope->lines[0]->disposition)->toBe(LineDisposition::ManualQuote)
        ->and($scope->lines[0]->photoRequest)->toBeNull()
        ->and($scope->readiness)->toBe(RequestReadiness::ManualQuote);
});

it('adds a needs-photos placeholder for a requested service no photo shows (R3)', function () {
    $observation = observationWith(['requested_in_sentence' => ['yard_cleanup', 'shrub_trimming', 'bed_weeding']]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());
    $placeholder = $scope->lines[3];

    expect($placeholder->type)->toBe(ServiceType::BedWeeding)
        ->and($placeholder->section)->toBeNull()
        ->and($placeholder->isPlaceholder())->toBeTrue()
        ->and($placeholder->disposition)->toBe(LineDisposition::NeedsPhotos)
        ->and($placeholder->checks[0]->rule)->toBe(ReadinessRule::RequestedUnseen)
        ->and($placeholder->photoRequest?->message)->toBe('You asked for Bed weeding, but no photo shows it. Add one photo of that area.')
        ->and($scope->readiness)->toBe(RequestReadiness::Partial);
});

it('keeps an invalid line rejected even when its section is covered (R4 before the gate)', function () {
    $observation = observationWith(['requested_in_sentence' => ['yard_cleanup', 'shrub_trimming'], 'service_lines' => [
        ['type' => 'yard_cleanup', 'section' => 'backyard', 'severity' => 'heavy', 'evidence' => [['photo' => 1, 'note' => 'leaves']]],
        ['type' => 'shrub_trimming', 'section' => 'backyard', 'quantity' => 3, 'size' => 'medium'],
    ]]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect($scope->rejected)->toHaveCount(1)
        ->and($scope->rejected[0]->reason)->toBe('A counted line needs exactly one counting view.')
        ->and($scope->lines[0]->disposition)->toBe(LineDisposition::Priceable)
        ->and($scope->lines[1]->type)->toBe(ServiceType::ShrubTrimming)
        ->and($scope->lines[1]->disposition)->toBe(LineDisposition::NeedsPhotos)
        ->and($scope->lines[1]->photoRequest?->message)->toBe('We could not read the Shrub trimming from these photos. Add one clear photo of it.')
        ->and($scope->readiness)->toBe(RequestReadiness::Partial);
});

it('marks services the customer did not ask for as suggested, never priced', function () {
    $observation = observationWith(['requested_in_sentence' => ['shrub_trimming']]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect($scope->lines[0]->disposition)->toBe(LineDisposition::Suggested)
        ->and($scope->lines[0]->photoRequest)->toBeNull()
        ->and($scope->lines[1]->disposition)->toBe(LineDisposition::Priceable)
        ->and($scope->lines[2]->disposition)->toBe(LineDisposition::Suggested)
        ->and($scope->priceableLines())->toHaveCount(1)
        ->and($scope->readiness)->toBe(RequestReadiness::Ready);
});

it('sends every line in a section with a hazard to a pro', function () {
    $observation = observationWith(['hazards' => [['section' => 'backyard', 'note' => 'exposed wiring near the shed', 'evidence' => [['photo' => 1, 'note' => 'cable on the ground']]]]]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect($scope->lines[0]->disposition)->toBe(LineDisposition::ManualQuote)
        ->and($scope->lines[0]->note)->toBe('A hazard was spotted in the backyard, so a pro has to look first.');
});

it('treats an uncertain branch as a pro quote without repeating the model note to the customer', function () {
    $observation = observationWith(['service_lines' => [
        ['type' => 'branch_removal', 'section' => 'backyard', 'quantity' => 1, 'size' => 'medium', 'counting_evidence' => ['photo' => 2, 'note' => 'branch'], 'uncertain' => 'I am 40% sure this is a branch'],
    ], 'requested_in_sentence' => ['branch_removal']]);

    $scope = (new ScopeBuilder)->build($observation, workedProfile());

    expect($scope->lines[0]->disposition)->toBe(LineDisposition::ManualQuote)
        ->and($scope->lines[0]->note)->toBe('The branch could not be judged from the photos, so a pro quotes it.')
        ->and($scope->lines[0]->uncertain)->toBe('I am 40% sure this is a branch');
});

it('rolls dispositions up into request readiness', function (array $dispositions, RequestReadiness $readiness) {
    $lines = array_map(fn (LineDisposition $disposition) => new ScopeLine(
        id: 'x', type: ServiceType::YardCleanup, section: Section::Backyard,
        observed: new LineValues(null, null, null), current: new LineValues(null, null, null),
        disposition: $disposition, checks: [], photoRequest: null, note: null,
        countingEvidence: null, supportingEvidence: [], evidence: [], uncertain: null, requested: true,
    ), $dispositions);

    expect(ReadinessRollup::from($lines))->toBe($readiness);
})->with([
    'all priceable' => [[LineDisposition::Priceable, LineDisposition::Priceable], RequestReadiness::Ready],
    'priceable plus suggested' => [[LineDisposition::Priceable, LineDisposition::Suggested], RequestReadiness::Ready],
    'priceable plus manual' => [[LineDisposition::Priceable, LineDisposition::ManualQuote], RequestReadiness::Partial],
    'priceable plus needs photos' => [[LineDisposition::Priceable, LineDisposition::NeedsPhotos], RequestReadiness::Partial],
    'needs photos and manual' => [[LineDisposition::NeedsPhotos, LineDisposition::ManualQuote], RequestReadiness::NeedsPhotos],
    'only manual' => [[LineDisposition::ManualQuote], RequestReadiness::ManualQuote],
    'only rejected' => [[LineDisposition::Rejected], RequestReadiness::ManualQuote],
    'nothing' => [[], RequestReadiness::ManualQuote],
]);

it('represents an unreadable observation as a manual quote request', function () {
    $scope = JobScope::unreadable(workedProfile(), 'The model output could not be read twice.');

    expect($scope->readiness)->toBe(RequestReadiness::ManualQuote)
        ->and($scope->lines)->toBe([])
        ->and($scope->requestNote)->toBe('The model output could not be read twice.');
});

it('treats a cleanup request as covering fallen branches, so the worked example is partial', function () {
    expect(workedObservation()->requestedInSentence)->toBe([ServiceType::YardCleanup, ServiceType::ShrubTrimming])
        ->and(ScopeBuilder::requestedServices(workedObservation()))->toBe([ServiceType::YardCleanup, ServiceType::ShrubTrimming, ServiceType::BranchRemoval])
        ->and(ServiceType::BranchRemoval->implies())->toBe([]);

    $scope = (new ScopeBuilder)->build(workedObservation(), workedProfile());

    expect($scope->lines[2]->requested)->toBeTrue()
        ->and($scope->lines[2]->disposition)->toBe(LineDisposition::ManualQuote)
        ->and($scope->readiness)->toBe(RequestReadiness::Partial);
});
