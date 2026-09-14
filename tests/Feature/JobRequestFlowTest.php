<?php

use App\Extraction\FixtureExtractor;
use App\Extraction\YardObservationAgent;
use App\Models\JobRequest;
use App\Requests\PhotoStore;
use App\Scoping\Data\PhotoInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\ProviderConnectionException;

beforeEach(function () {
    Storage::fake('local');
    $this->fixtures = scratchDirectory();
    config()->set('yardscope.extraction.fixtures', $this->fixtures);
});

/**
 * @return list<UploadedFile>
 */
function demoUploads(int $count = 3): array
{
    return array_map(fn (int $number): UploadedFile => UploadedFile::fake()->image("yard-{$number}.jpg", 640 + $number, 480), range(1, $count));
}

function recordFor(array $uploads, string $sentence, array $observation, string $directory): void
{
    // Recordings key on the stored bytes; store a copy the same way the app will, then key on that.
    $store = new PhotoStore;
    $photos = [];

    foreach ($uploads as $index => $upload) {
        $stored = $store->store($upload, 'preview', $index + 1);
        $photos[] = new PhotoInput($index + 1, Storage::disk('local')->path($stored['path']), 'image/jpeg');
    }

    file_put_contents((new FixtureExtractor($directory))->pathFor($photos, $sentence), json_encode(['observation' => $observation]));
    Storage::disk('local')->deleteDirectory('requests/preview');
}

it('prices the worked example from three photos and one sentence', function () {
    $uploads = demoUploads();
    recordFor($uploads, 'My backyard is a mess. Clean it up and trim whatever needs trimming.', workedExample()['observation'], $this->fixtures);

    $response = $this->post('/requests', ['sentence' => 'My backyard is a mess. Clean it up and trim whatever needs trimming.', 'photos' => $uploads]);

    $request = JobRequest::sole();
    $response->assertRedirect(route('requests.show', $request));

    $this->get(route('requests.show', $request))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('result')
            ->where('request.readiness', 'partial')
            ->where('request.readinessLabel', 'Priced work bookable now')
            ->where('request.estimate.price', '$165')
            ->where('request.estimate.hours', '2 to 3.5 h')
            ->where('request.cta.label', 'Book priced work, $165')
            ->where('request.cta.enabled', true)
            ->where('request.excludedSummary', 'Branch removal is not included. A pro will quote it separately.')
            ->where('request.lines.0.disposition', 'priceable')
            ->where('request.lines.0.summary', 'Heavy cleanup')
            ->where('request.lines.1.summary', '4 shrubs, medium')
            ->where('request.lines.2.disposition', 'manual_quote')
            ->where('request.lines.2.note', 'Large branches are quoted on site.')
            ->where('request.access.narrowGatePossible', true)
            ->has('request.photos', 3));

    expect($request->readiness)->toBe('partial')
        ->and(Storage::disk('local')->exists("requests/{$request->id}/photo-1.jpg"))->toBeTrue();
});

it('strips metadata from uploaded photos by re-encoding them', function () {
    $jpeg = UploadedFile::fake()->image('with-exif.jpg', 320, 240);
    $bytes = file_get_contents($jpeg->getPathname());
    $exif = "\xFF\xE1".pack('n', 2 + 6 + 8).'Exif'."\0\0".'MM'."\0\x2A\0\0\0\x08";
    file_put_contents($jpeg->getPathname(), substr($bytes, 0, 2).$exif.substr($bytes, 2));
    expect(strpos((string) file_get_contents($jpeg->getPathname()), 'Exif'))->not->toBeFalse();

    $stored = (new PhotoStore)->store($jpeg, 'abc', 1);
    $result = file_get_contents(Storage::disk('local')->path($stored['path']));

    expect(strpos((string) $result, 'Exif'))->toBeFalse()
        ->and(getimagesize(Storage::disk('local')->path($stored['path']))[2])->toBe(IMAGETYPE_JPEG);
});

it('explains when nothing was recorded for these photos instead of guessing', function () {
    $this->post('/requests', ['sentence' => 'Clean up the whole backyard please', 'photos' => demoUploads()])
        ->assertRedirect()
        ->assertSessionHasErrors(['photos' => 'No recorded observation for these photos and this sentence. Run it live once with yardscope:record, or use one of the demo sets.']);

    expect(JobRequest::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('routes a model failure to a pro quote instead of an error page', function () {
    config()->set('yardscope.extraction.live', true);
    Ai::fakeAgent(YardObservationAgent::class, [fn () => throw new ProviderConnectionException('timed out')]);

    $this->post('/requests', ['sentence' => 'Clean up the whole backyard please', 'photos' => demoUploads(2)])->assertRedirect();
    $request = JobRequest::sole();

    $this->get(route('requests.show', $request))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('request.readiness', 'manual_quote')
            ->where('request.requestNote', 'We could not read these photos automatically. A pro will quote this job on site.')
            ->where('request.cta.enabled', false));

    expect($request->latestRun()?->failure)->toContain('timed out');
});

it('validates the sentence and the photos', function (array $input, string $field) {
    $this->post('/requests', $input)->assertSessionHasErrors($field);
})->with([
    'short sentence' => [fn () => ['sentence' => 'Mow it', 'photos' => demoUploads()], 'sentence'],
    'one photo' => [fn () => ['sentence' => 'Clean up the whole backyard', 'photos' => demoUploads(1)], 'photos'],
    'five photos' => [fn () => ['sentence' => 'Clean up the whole backyard', 'photos' => demoUploads(5)], 'photos'],
    'not an image' => [fn () => ['sentence' => 'Clean up the whole backyard', 'photos' => [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf'), UploadedFile::fake()->image('a.jpg')]], 'photos.0'],
]);

it('serves stored photos and hides unknown ones', function () {
    $uploads = demoUploads(2);
    recordFor($uploads, 'Clean up the whole backyard', workedExample()['observation'], $this->fixtures);
    $this->post('/requests', ['sentence' => 'Clean up the whole backyard', 'photos' => $uploads]);
    $request = JobRequest::sole();

    $this->get(route('requests.photo', [$request, 1]))->assertOk()->assertHeader('content-type', 'image/jpeg');
    $this->get(route('requests.photo', [$request, 9]))->assertNotFound();
});
