<?php

use App\Extraction\YardObservationAgent;
use App\Scoping\Data\PhotoInput;
use App\Scoping\VisionExtractor;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\ProviderConnectionException;

beforeEach(function () {
    $this->directory = scratchDirectory();
    $this->photos = scratchPhotos(3, $this->directory);
    config()->set('yardscope.extraction.fixtures', $this->directory);
});

it('records the model answer and replays it in fixture mode', function () {
    Ai::fakeAgent(YardObservationAgent::class, [workedExample()['observation']]);
    $paths = array_map(fn (PhotoInput $photo): string => $photo->path, $this->photos);

    $this->artisan('yardscope:record', ['sentence' => 'My backyard is a mess.', 'photos' => $paths])
        ->expectsOutputToContain('3 service lines, 0 rejected, 3 usable photos.')
        ->assertSuccessful();

    $written = glob($this->directory.'/*.json');
    $saved = json_decode((string) file_get_contents($written[0]), true);

    expect($written)->toHaveCount(1)
        ->and($saved['observation'])->toBe(workedExample()['observation'])
        ->and($saved['sentence'])->toBe('My backyard is a mess.')
        ->and($saved['photos'])->toBe(['photo-1.png', 'photo-2.png', 'photo-3.png'])
        ->and(app(VisionExtractor::class)->extract($this->photos, 'my backyard is a mess')->lines)->toHaveCount(3);
});

it('refuses a missing photo and the wrong number of photos', function () {
    $paths = array_map(fn (PhotoInput $photo): string => $photo->path, $this->photos);

    $this->artisan('yardscope:record', ['sentence' => 'x', 'photos' => [$paths[0], $this->directory.'/missing.png']])
        ->expectsOutputToContain('Photo not found')
        ->assertFailed();
    $this->artisan('yardscope:record', ['sentence' => 'x', 'photos' => [$paths[0]]])
        ->expectsOutputToContain('Record 2 to 4 photos')
        ->assertFailed();
});

it('fails plainly when the model cannot be reached', function () {
    Ai::fakeAgent(YardObservationAgent::class, [fn () => throw new ProviderConnectionException('timed out')]);
    $paths = array_map(fn (PhotoInput $photo): string => $photo->path, $this->photos);

    $this->artisan('yardscope:record', ['sentence' => 'x', 'photos' => $paths])
        ->expectsOutputToContain('The vision model could not be reached: timed out')
        ->assertFailed();

    expect(glob($this->directory.'/*.json'))->toBe([]);
});
