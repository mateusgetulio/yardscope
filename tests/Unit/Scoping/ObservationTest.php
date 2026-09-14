<?php

use App\Scoping\Data\Observation;
use App\Scoping\Data\ObservedLine;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use App\Scoping\Exceptions\InvalidObservation;

it('parses the worked example', function () {
    $observation = workedObservation();

    expect($observation->photos)->toHaveCount(3)
        ->and($observation->requestedInSentence)->toBe([ServiceType::YardCleanup, ServiceType::ShrubTrimming])
        ->and($observation->lines)->toHaveCount(3)
        ->and($observation->rejected)->toBe([])
        ->and($observation->lines[1]->values->quantity)->toBe(4)
        ->and($observation->lines[1]->values->size)->toBe(Size::Medium)
        ->and($observation->lines[1]->countingEvidence?->photo)->toBe(1)
        ->and($observation->lines[0]->values->severity)->toBe(Severity::Heavy)
        ->and($observation->lines[2]->uncertain)->toBe('branch diameter hard to judge')
        ->and($observation->access->narrowGatePossible)->toBeTrue()
        ->and($observation->covers(Section::Backyard))->toBeTrue()
        ->and($observation->covers(Section::FrontYard))->toBeFalse();
});

it('keeps a bad line as rejected instead of failing the whole observation', function (mixed $line, string $reason) {
    $data = workedExample()['observation'];
    $data['service_lines'] = [$line];

    $observation = Observation::fromArray($data);

    expect($observation->lines)->toBe([])
        ->and($observation->rejected)->toHaveCount(1)
        ->and($observation->rejected[0]->reason)->toBe($reason);
})->with([
    'unknown service type' => [['type' => 'pool_cleaning', 'section' => 'backyard'], 'Unknown service type [pool_cleaning].'],
    'unknown section' => [['type' => 'yard_cleanup', 'section' => 'roof', 'severity' => 'heavy', 'evidence' => [['photo' => 1, 'note' => 'x']]], 'The section must be front_yard, backyard or side_yard.'],
    'count without a counting view' => [['type' => 'shrub_trimming', 'section' => 'backyard', 'quantity' => 4, 'size' => 'medium', 'supporting_evidence' => [['photo' => 1, 'note' => 'four shrubs']]], 'A counted line needs exactly one counting view.'],
    'count out of range' => [['type' => 'shrub_trimming', 'section' => 'backyard', 'quantity' => 0, 'size' => 'medium', 'counting_evidence' => ['photo' => 1, 'note' => 'x']], 'A counted line needs a quantity between 1 and 20.'],
    'count without a size' => [['type' => 'branch_removal', 'section' => 'backyard', 'quantity' => 1, 'counting_evidence' => ['photo' => 1, 'note' => 'x']], 'A counted line needs a size of small, medium or large.'],
    'cleanup without evidence' => [['type' => 'yard_cleanup', 'section' => 'backyard', 'severity' => 'heavy'], 'A cleanup line needs at least one piece of evidence.'],
    'cleanup without severity' => [['type' => 'yard_cleanup', 'section' => 'backyard', 'evidence' => [['photo' => 1, 'note' => 'x']]], 'A cleanup line needs a severity of light, moderate or heavy.'],
    'evidence on a photo that does not exist' => [['type' => 'yard_cleanup', 'section' => 'backyard', 'severity' => 'heavy', 'evidence' => [['photo' => 9, 'note' => 'x']]], 'Evidence points at photo 9, which is not in the request.'],
    'not an object' => ['not a line', 'A service line must be an object.'],
]);

it('parses bed weeding with a severity and a counting view', function () {
    $data = workedExample()['observation'];
    $data['service_lines'] = [['type' => 'bed_weeding', 'section' => 'backyard', 'quantity' => 2, 'severity' => 'moderate', 'counting_evidence' => ['photo' => 1, 'note' => 'two beds along the fence']]];

    $observation = Observation::fromArray($data);

    expect($observation->lines[0])->toBeInstanceOf(ObservedLine::class)
        ->and($observation->lines[0]->values->quantity)->toBe(2)
        ->and($observation->lines[0]->values->severity)->toBe(Severity::Moderate);
});

it('rejects structural problems so the caller can retry', function (array $data, string $message) {
    expect(fn () => Observation::fromArray($data))->toThrow(InvalidObservation::class, $message);
})->with([
    'photos missing' => [['service_lines' => [], 'requested_in_sentence' => []], 'photos must be a list.'],
    'photo without a number' => [['photos' => [['view' => 'wide', 'sections' => ['backyard'], 'usable' => true]], 'service_lines' => [], 'requested_in_sentence' => []], 'A photo needs an integer photo.'],
    'unknown view' => [['photos' => [['photo' => 1, 'view' => 'aerial', 'sections' => ['backyard'], 'usable' => true]], 'service_lines' => [], 'requested_in_sentence' => []], 'A photo view must be wide, medium or close, got [aerial].'],
    'photo section unknown' => [['photos' => [['photo' => 1, 'view' => 'wide', 'sections' => ['roof'], 'usable' => true]], 'service_lines' => [], 'requested_in_sentence' => []], 'A section must be front_yard, backyard or side_yard.'],
    'duplicate photo number' => [['photos' => [['photo' => 1, 'view' => 'wide', 'sections' => ['backyard'], 'usable' => true], ['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true]], 'service_lines' => [], 'requested_in_sentence' => []], 'Each photo number can appear only once.'],
    'hazard without a note' => [['photos' => [['photo' => 1, 'view' => 'wide', 'sections' => ['backyard'], 'usable' => true]], 'service_lines' => [], 'requested_in_sentence' => [], 'hazards' => [['section' => 'backyard']]], 'A hazard needs a non-empty note.'],
]);

it('keeps unknown requested services aside instead of failing or forgetting them', function () {
    $data = workedExample()['observation'];
    $data['requested_in_sentence'] = ['yard_cleanup', 'pool_cleaning', 'yard_cleanup', 'pool_cleaning'];

    $observation = Observation::fromArray($data);

    expect($observation->requestedInSentence)->toBe([ServiceType::YardCleanup])
        ->and($observation->unsupportedRequests)->toBe(['pool_cleaning']);
});

it('drops fields that do not belong to the service type', function () {
    $data = workedExample()['observation'];
    $data['service_lines'] = [
        ['type' => 'yard_cleanup', 'section' => 'backyard', 'severity' => 'heavy', 'size' => 'large', 'quantity' => 3, 'counting_evidence' => ['photo' => 1, 'note' => 'x'], 'evidence' => [['photo' => 1, 'note' => 'leaves']]],
        ['type' => 'shrub_trimming', 'section' => 'backyard', 'quantity' => 2, 'size' => 'small', 'counting_evidence' => ['photo' => 1, 'note' => 'two'], 'evidence' => [['photo' => 2, 'note' => 'stray']]],
    ];

    $observation = Observation::fromArray($data);

    expect($observation->lines[0]->values->size)->toBeNull()
        ->and($observation->lines[0]->values->quantity)->toBeNull()
        ->and($observation->lines[0]->countingEvidence)->toBeNull()
        ->and($observation->lines[1]->evidence)->toBe([])
        ->and($observation->lines[1]->supportingEvidence)->toHaveCount(1);
});

it('counts only usable wide or medium photos as covering a section', function () {
    $data = workedExample()['observation'];
    $data['photos'] = [
        ['photo' => 1, 'view' => 'close', 'sections' => ['backyard'], 'usable' => true],
        ['photo' => 2, 'view' => 'wide', 'sections' => ['backyard'], 'usable' => false],
        ['photo' => 3, 'view' => 'medium', 'sections' => ['front_yard'], 'usable' => true],
    ];
    $data['service_lines'] = [];

    $observation = Observation::fromArray($data);

    expect($observation->covers(Section::Backyard))->toBeFalse()
        ->and($observation->covers(Section::FrontYard))->toBeTrue()
        ->and($observation->usablePhotos())->toHaveCount(2);
});
