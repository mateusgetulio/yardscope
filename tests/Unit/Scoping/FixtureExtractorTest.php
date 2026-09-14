<?php

use App\Extraction\FixtureExtractor;
use App\Scoping\Data\PhotoInput;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\NoRecordedObservation;

beforeEach(function () {
    $this->directory = scratchDirectory();
    $this->photos = scratchPhotos(3, $this->directory);
});

it('replays the recorded observation for the same photos and sentence', function () {
    $extractor = new FixtureExtractor($this->directory);
    file_put_contents($extractor->pathFor($this->photos, 'Clean it up'), json_encode(['observation' => workedExample()['observation']]));

    $observation = $extractor->extract($this->photos, '  clean IT up.  ');

    expect($observation->lines)->toHaveCount(3);
});

it('keys recordings by photo bytes, not file names', function () {
    $renamed = array_map(fn (PhotoInput $photo): PhotoInput => new PhotoInput($photo->number, $photo->path.'.copy', 'image/png'), $this->photos);

    foreach ($this->photos as $photo) {
        copy($photo->path, $photo->path.'.copy');
    }

    expect(FixtureExtractor::keyFor($renamed, 'Clean it up'))->toBe(FixtureExtractor::keyFor($this->photos, 'Clean it up'))
        ->and(FixtureExtractor::keyFor($this->photos, 'Trim the shrubs'))->not->toBe(FixtureExtractor::keyFor($this->photos, 'Clean it up'))
        ->and(FixtureExtractor::keyFor(array_reverse($this->photos), 'Clean it up'))->not->toBe(FixtureExtractor::keyFor($this->photos, 'Clean it up'));
});

it('refuses to guess when nothing was recorded', function () {
    expect(fn () => (new FixtureExtractor($this->directory))->extract($this->photos, 'Clean it up'))
        ->toThrow(NoRecordedObservation::class, 'No recorded observation for these photos and this sentence.');
});

it('reports an unreadable or invalid recording', function () {
    $extractor = new FixtureExtractor($this->directory);
    file_put_contents($extractor->pathFor($this->photos, 'broken'), 'not json');
    file_put_contents($extractor->pathFor($this->photos, 'invalid'), json_encode(['observation' => ['photos' => 'nope']]));

    expect(fn () => $extractor->extract($this->photos, 'broken'))->toThrow(ExtractionFailed::class, 'is not readable')
        ->and(fn () => $extractor->extract($this->photos, 'invalid'))->toThrow(ExtractionFailed::class, 'photos must be a list.');
});

it('names a photo it cannot read', function () {
    $missing = [new PhotoInput(1, $this->directory.'/nope.png', 'image/png')];

    expect(fn () => FixtureExtractor::keyFor($missing, 'x'))->toThrow(ExtractionFailed::class, 'Photo 1 at');
});
