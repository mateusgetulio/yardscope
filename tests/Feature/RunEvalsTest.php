<?php

use App\Extraction\FixtureExtractor;
use App\Extraction\YardObservationAgent;
use App\Scoping\Data\PhotoInput;
use Laravel\Ai\Ai;

beforeEach(function () {
    $this->root = scratchDirectory();
    config()->set('yardscope.evals.sets', $this->root.'/sets');
    config()->set('yardscope.evals.fixtures', $this->root.'/fixtures');
    config()->set('yardscope.evals.results', $this->root.'/results');
    mkdir($this->root.'/sets');
    mkdir($this->root.'/fixtures');
});

/**
 * Writes one labeled set with tiny photos and returns its photo inputs.
 *
 * @return list<PhotoInput>
 */
function labeledSet(string $root, string $slug, array $labels, int $photos = 3): array
{
    $directory = "{$root}/sets/{$slug}";
    mkdir($directory, 0755, true);
    $inputs = scratchPhotos($photos, $directory);

    // Scratch photos are identical across sets; the slug makes each set's bytes, and so its recording key, its own.
    foreach ($inputs as $photo) {
        file_put_contents($photo->path, $slug, FILE_APPEND);
    }

    $labels['photos'] = array_map(fn (PhotoInput $photo): string => basename($photo->path), $inputs);
    file_put_contents("{$directory}/labels.json", json_encode($labels));

    return $inputs;
}

function recordEval(string $root, array $photos, string $sentence, array $observation): void
{
    file_put_contents((new FixtureExtractor($root.'/fixtures'))->pathFor($photos, $sentence), json_encode(['observation' => $observation]));
}

function workedLabels(string $readiness = 'partial'): array
{
    return [
        'scenario' => 'cleanup_plus_shrubs',
        'sentence' => 'My backyard is a mess. Clean it up and trim whatever needs trimming.',
        'expected' => [
            'readiness' => $readiness,
            'unusable_photos' => [],
            'lines' => [
                ['type' => 'yard_cleanup', 'section' => 'backyard', 'severity' => 'heavy', 'disposition' => 'priceable'],
                ['type' => 'shrub_trimming', 'section' => 'backyard', 'quantity' => 4, 'size' => 'medium', 'counting_photo' => 1, 'disposition' => 'priceable'],
                ['type' => 'branch_removal', 'section' => 'backyard', 'quantity' => 1, 'disposition' => 'manual_quote'],
            ],
        ],
    ];
}

it('replays recorded answers, scores every set and writes the results file', function () {
    $sentence = workedLabels()['sentence'];
    $good = labeledSet($this->root, 'a-worked', workedLabels());
    recordEval($this->root, $good, $sentence, workedExample()['observation']);

    // The second set expects only a cleanup; the model also reports shrubs (hallucinated) and counts the branch wrong.
    $labels = workedLabels();
    $labels['scenario'] = 'happy_path_cleanup';
    $labels['expected']['readiness'] = 'ready';
    $labels['expected']['lines'] = [
        ['type' => 'yard_cleanup', 'section' => 'backyard', 'severity' => 'heavy', 'disposition' => 'priceable'],
        ['type' => 'branch_removal', 'section' => 'backyard', 'quantity' => 2, 'disposition' => 'manual_quote'],
    ];
    $second = labeledSet($this->root, 'b-cleanup', $labels);
    // A third set marks the shrubs optional: volunteered or not, they are neither missed nor hallucinated.
    $labels['expected']['lines'][] = ['type' => 'shrub_trimming', 'section' => 'backyard', 'optional' => true];
    $labels['expected']['lines'][1]['quantity'] = 1;
    $third = labeledSet($this->root, 'c-optional', $labels);
    recordEval($this->root, $third, $sentence, workedExample()['observation']);
    recordEval($this->root, $second, $sentence, workedExample()['observation']);

    // Output expectations are ordered and each consumes one line, so a line is matched once.
    $this->artisan('yardscope:eval', ['--fixtures' => true])
        ->expectsOutputToContain('a-worked')
        ->expectsOutputToContain('b-cleanup                        partial (expected ready); hallucinated shrub_trimming@backyard; branch_removal@backyard counted 1 for 2')
        ->expectsOutputToContain('hallucinated_lines           1')
        ->assertSuccessful();

    $files = glob($this->root.'/results/*.json');
    $results = json_decode((string) file_get_contents($files[0]), true);

    expect($files)->toHaveCount(1)
        ->and($results['mode'])->toBe('fixtures')
        ->and($results['metrics']['sets'])->toBe(3)
        ->and($results['metrics']['schema_valid_rate'])->toBe(1.0)
        ->and($results['metrics']['service_precision'])->toBe(round(8 / 9, 3))
        ->and($results['metrics']['severity_accuracy'])->toBe(1.0)
        ->and($results['metrics']['size_accuracy'])->toBe(1.0)
        ->and($results['metrics']['service_recall'])->toBe(1.0)
        ->and($results['metrics']['hallucinated_lines'])->toBe(1)
        ->and($results['metrics']['count_exact_rate'])->toBe(round(3 / 4, 3))
        ->and($results['metrics']['count_within_one_rate'])->toBe(1.0)
        ->and($results['metrics']['disposition_accuracy'])->toBe(1.0)
        ->and($results['metrics']['readiness_accuracy'])->toBe(round(1 / 3, 3))
        ->and($results['metrics']['counting_photo_accuracy'])->toBe(1.0)
        ->and($results['metrics']['unusable_photo_accuracy'])->toBe(1.0)
        ->and($results['metrics']['photo_request_accuracy'])->toBeNull()
        ->and($results['sets'][0]['slug'])->toBe('a-worked')
        ->and($results['sets'][1]['hallucinated'])->toBe(['shrub_trimming@backyard'])
        ->and($results['sets'][2]['hallucinated'])->toBe([])
        ->and($results['sets'][2]['optional_services'])->toBe(['shrub_trimming@backyard'])
        ->and($results['sets'][0]['observation']['service_lines'])->toHaveCount(3);
});

it('scores the photo request and the unusable photos, and marks a missing recording instead of guessing', function () {
    $labels = workedLabels('needs_photos');
    $labels['scenario'] = 'missing_wide_shot';
    $labels['expected']['unusable_photos'] = [3];
    $labels['expected']['photo_request'] = ['service' => 'yard_cleanup', 'section' => 'backyard'];
    $labels['expected']['lines'] = [['type' => 'yard_cleanup', 'section' => 'backyard', 'disposition' => 'needs_photos']];
    $photos = labeledSet($this->root, 'a-missing-wide', $labels);
    $observation = workedExample()['observation'];
    $observation['photos'][0]['view'] = 'close';
    $observation['photos'][2]['usable'] = false;
    recordEval($this->root, $photos, $labels['sentence'], $observation);

    labeledSet($this->root, 'b-unrecorded', workedLabels());

    $this->artisan('yardscope:eval', ['--fixtures' => true])
        ->expectsOutputToContain('no observation: No recorded observation')
        ->assertFailed();

    $results = json_decode((string) file_get_contents(glob($this->root.'/results/*.json')[0]), true);

    expect($results['metrics']['schema_valid_rate'])->toBe(0.5)
        ->and($results['sets'][0]['photo_request_correct'])->toBeTrue()
        ->and($results['sets'][0]['unusable_photos_correct'])->toBeTrue()
        ->and($results['sets'][0]['readiness_correct'])->toBeTrue()
        ->and($results['sets'][1]['schema_valid'])->toBeFalse()
        ->and($results['sets'][1]['observed_services'])->toBe([]);
});

it('runs live through the configured extractor and records every answer for replay', function () {
    config()->set('yardscope.extraction.driver', 'api');
    Ai::fakeAgent(YardObservationAgent::class, [workedExample()['observation']]);
    labeledSet($this->root, 'a-worked', workedLabels());

    $this->artisan('yardscope:eval', ['--live' => true])->assertSuccessful();

    $fixtures = glob($this->root.'/fixtures/*.json');
    $results = json_decode((string) file_get_contents(glob($this->root.'/results/*.json')[0]), true);

    expect($fixtures)->toHaveCount(1)
        ->and(json_decode((string) file_get_contents($fixtures[0]), true)['set'])->toBe('a-worked')
        ->and($results['mode'])->toBe('live')
        ->and($results['driver'])->toBe('api')
        ->and($results['metrics']['readiness_accuracy'])->toBe(1.0);

    // The live run's recordings replay to the same score.
    $this->artisan('yardscope:eval', ['--fixtures' => true])->assertSuccessful();
});

it('refuses to run without a mode or without sets', function () {
    $this->artisan('yardscope:eval')->expectsOutputToContain('Choose one of --live or --fixtures.')->assertFailed();
    $this->artisan('yardscope:eval', ['--fixtures' => true])->expectsOutputToContain('No labeled sets under')->assertFailed();
});

it('counts a line the model reported twice as a duplicate, not as a second match or a hallucination', function () {
    $labels = workedLabels();
    $photos = labeledSet($this->root, 'a-twice', $labels);
    $observation = workedExample()['observation'];
    $observation['service_lines'][] = $observation['service_lines'][0];
    recordEval($this->root, $photos, $labels['sentence'], $observation);

    $this->artisan('yardscope:eval', ['--fixtures' => true])->expectsOutputToContain('1 duplicate line')->assertSuccessful();

    $results = json_decode((string) file_get_contents(glob($this->root.'/results/*.json')[0]), true);

    expect($results['metrics']['service_precision'])->toBe(1.0)
        ->and($results['metrics']['service_recall'])->toBe(1.0)
        ->and($results['metrics']['duplicate_lines'])->toBe(1)
        ->and($results['metrics']['hallucinated_lines'])->toBe(0)
        ->and($results['sets'][0]['observed_services'])->toHaveCount(3);
});

it('never scores a requested-but-unseen placeholder as a hallucination', function () {
    // Nothing observed, so the pipeline adds placeholders for the two requested services.
    $labels = workedLabels('needs_photos');
    $labels['expected']['lines'] = [['type' => 'yard_cleanup', 'section' => null, 'disposition' => 'needs_photos']];
    $labels['expected']['photo_request'] = ['service' => 'yard_cleanup'];
    $photos = labeledSet($this->root, 'a-nothing', $labels);
    $observation = workedExample()['observation'];
    $observation['service_lines'] = [];
    recordEval($this->root, $photos, $labels['sentence'], $observation);

    $this->artisan('yardscope:eval', ['--fixtures' => true])->assertSuccessful();

    $results = json_decode((string) file_get_contents(glob($this->root.'/results/*.json')[0]), true);

    expect($results['sets'][0]['observed_services'])->toBe([])
        ->and($results['sets'][0]['hallucinated'])->toBe([])
        ->and($results['sets'][0]['dispositions'][0]['observed'])->toBe('needs_photos')
        ->and($results['sets'][0]['photo_request_correct'])->toBeTrue()
        ->and($results['metrics']['service_precision'])->toBeNull()
        ->and($results['metrics']['readiness_accuracy'])->toBe(1.0);
});

it('fails when the replay drifts from the committed results', function () {
    $labels = workedLabels();
    $photos = labeledSet($this->root, 'a-worked', $labels);
    recordEval($this->root, $photos, $labels['sentence'], workedExample()['observation']);

    $this->artisan('yardscope:eval', ['--fixtures' => true])->assertSuccessful();
    $this->artisan('yardscope:eval', ['--fixtures' => true, '--expect' => 'latest'])->assertSuccessful();

    // A rate card change moves nothing the guard watches; a changed answer does.
    config()->set('yardscope.rates.hours.shrub_trimming.medium', [5.0, 6.0]);
    $this->artisan('yardscope:eval', ['--fixtures' => true, '--expect' => 'latest'])->assertSuccessful();

    $observation = workedExample()['observation'];
    $observation['service_lines'][1]['size'] = 'large';
    recordEval($this->root, $photos, $labels['sentence'], $observation);
    $this->artisan('yardscope:eval', ['--fixtures' => true, '--expect' => 'latest'])
        ->expectsOutputToContain('a-worked dispositions:')
        ->assertFailed();

    $this->artisan('yardscope:eval', ['--fixtures' => true, '--expect' => $this->root.'/missing.json'])
        ->expectsOutputToContain('No results file to compare with')
        ->assertFailed();
});

it('refuses labels the pipeline does not understand', function () {
    $labels = workedLabels();
    $labels['expected']['lines'][0]['disposition'] = 'priced';
    labeledSet($this->root, 'a-typo', $labels);

    $this->artisan('yardscope:eval', ['--fixtures' => true])
        ->expectsOutputToContain('line 0 disposition ["priced"] is not one of')
        ->assertFailed();
});
