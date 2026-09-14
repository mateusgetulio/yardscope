<?php

use App\Extraction\FixtureExtractor;
use App\Extraction\YardObservationAgent;
use App\Models\JobRequest;
use App\Requests\PhotoStore;
use App\Scoping\Data\PhotoInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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

/**
 * Posts the worked example and returns the stored request.
 */
function submittedWorkedExample(string $directory, ?array $observation = null, string $sentence = 'My backyard is a mess. Clean it up and trim whatever needs trimming.'): JobRequest
{
    $uploads = demoUploads();
    recordFor($uploads, $sentence, $observation ?? workedExample()['observation'], $directory);
    test()->post('/requests', ['sentence' => $sentence, 'photos' => $uploads]);

    return JobRequest::sole();
}

function correct(JobRequest $request, string $lineId, string $field, string $customerValue, string $modelValue = '', ?string $reason = null): TestResponse
{
    return test()->post(route('requests.corrections.store', $request), [
        'line_id' => $lineId, 'field' => $field, 'model_value' => $modelValue, 'customer_value' => $customerValue, 'reason' => $reason,
    ]);
}

it('recomputes the price when the customer corrects a count up or down', function () {
    $request = submittedWorkedExample($this->fixtures);

    correct($request, 'line-2', 'quantity', '6', '4', 'Two more behind the shed')->assertRedirect(route('requests.show', $request));
    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.lines.1.summary', '6 shrubs, medium')
        ->where('request.lines.1.origin', 'customer_corrected')
        ->where('request.lines.1.observedSummary', '4 shrubs, medium')
        ->where('request.lines.1.lastReason', 'Two more behind the shed')
        ->where('request.estimate.priceCents', fn (int $cents): bool => $cents > 16500));

    correct($request, 'line-2', 'quantity', '2', '6')->assertRedirect();
    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.lines.1.summary', '2 shrubs, medium')
        ->where('request.estimate.priceCents', fn (int $cents): bool => $cents < 16500));

    expect($request->latestRun()?->corrections()->count())->toBe(2);
});

it('moves a line to a pro quote when a correction crosses a rule boundary', function () {
    $request = submittedWorkedExample($this->fixtures);

    correct($request, 'line-2', 'size', 'large', 'medium')->assertRedirect();

    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.lines.1.disposition', 'manual_quote')
        ->where('request.lines.1.note', 'Large shrubs are quoted on site.')
        ->where('request.excludedSummary', 'Shrub trimming and branch removal are not included. A pro will quote them separately.')
        ->where('request.estimate.priceCents', fn (int $cents): bool => $cents < 16500));
});

it('refuses a correction made against a stale value and stores nothing', function () {
    $request = submittedWorkedExample($this->fixtures);

    correct($request, 'line-2', 'quantity', '5', '3')
        ->assertSessionHasErrors(['correction' => 'Line [line-2] currently has quantity [4], not [3].']);
    correct($request, 'line-2', 'quantity', '25', '4')
        ->assertSessionHasErrors(['correction' => 'A Shrub trimming count must be between 1 and 20.']);

    expect($request->latestRun()?->corrections()->count())->toBe(0);
});

it('lets the customer remove a line, put it back, and add a suggested one', function () {
    $observation = workedExample()['observation'];
    $observation['requested_in_sentence'] = ['yard_cleanup'];
    $request = submittedWorkedExample($this->fixtures, $observation, 'Clean up my backyard please, it is a mess');

    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.lines.1.disposition', 'suggested')
        ->where('request.lines.1.canAdd', true)
        ->where('request.estimate.priceCents', fn (int $cents): bool => $cents < 16500));

    correct($request, 'line-2', 'added', '')->assertRedirect();
    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.lines.1.disposition', 'priceable')
        ->where('request.estimate.priceCents', 16500));

    correct($request, 'line-1', 'removed', '')->assertRedirect();
    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.lines.0.disposition', 'rejected')
        ->where('request.lines.0.removed', true)
        ->where('request.lines.0.note', 'Removed by the customer.')
        ->where('request.lines.0.canAdd', true));

    correct($request, 'line-1', 'added', '')->assertRedirect();
    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.lines.0.disposition', 'priceable')
        ->where('request.estimate.priceCents', 16500));
});

it('analyzes again after a photo is added and shows the checks improving', function () {
    $uploads = demoUploads();
    $sentence = 'My backyard is a mess. Clean it up and trim whatever needs trimming.';
    $closeOnly = workedExample()['observation'];
    $closeOnly['photos'][0]['view'] = 'close';
    recordFor($uploads, $sentence, $closeOnly, $this->fixtures);
    $this->post('/requests', ['sentence' => $sentence, 'photos' => $uploads]);
    $request = JobRequest::sole();

    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.readiness', 'needs_photos')
        ->where('request.lines.0.disposition', 'needs_photos')
        ->where('request.lines.0.photoRequest', 'One photo showing the whole backyard, taken from the far end.')
        ->where('request.estimate', null)
        ->where('request.cta.enabled', false));

    $fourth = UploadedFile::fake()->image('yard-4.jpg', 800, 600);
    $withWide = workedExample()['observation'];
    $withWide['photos'][0]['view'] = 'close';
    $withWide['photos'][] = ['photo' => 4, 'view' => 'wide', 'sections' => ['backyard'], 'usable' => true];
    recordFor([...$uploads, $fourth], $sentence, $withWide, $this->fixtures);

    $this->post(route('requests.photos.store', $request), ['photo' => $fourth])->assertRedirect(route('requests.show', $request));

    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.readiness', 'partial')
        ->where('request.lines.0.disposition', 'priceable')
        ->where('request.estimate.price', '$165')
        ->has('request.photos', 4)
        ->where('request.canAddPhoto', false));

    expect($request->runs()->count())->toBe(2);
});

it('keeps the photo set unchanged when the new set has no recording', function () {
    $request = submittedWorkedExample($this->fixtures);

    $this->post(route('requests.photos.store', $request), ['photo' => UploadedFile::fake()->image('yard-4.jpg')])
        ->assertSessionHasErrors('photo');

    expect($request->fresh()?->photos)->toHaveCount(3)
        ->and($request->runs()->count())->toBe(1)
        ->and(Storage::disk('local')->exists("requests/{$request->id}/photo-4.jpg"))->toBeFalse();
});

it('books priced work at the current price and freezes the scope', function () {
    $request = submittedWorkedExample($this->fixtures);

    $this->post(route('requests.book', $request))->assertRedirect(route('requests.booked', $request));

    $request->refresh();
    expect($request->booked_price_cents)->toBe(16500)
        ->and($request->booked_at)->not->toBeNull();

    $this->get(route('requests.booked', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->component('booked')
        ->where('request.booked', true)
        ->where('request.bookedPrice', '$165')
        ->where('request.excludedSummary', 'Branch removal is not included. A pro will quote it separately.'));

    correct($request, 'line-2', 'quantity', '6', '4')->assertSessionHasErrors('correction');
    $this->post(route('requests.photos.store', $request), ['photo' => UploadedFile::fake()->image('yard-4.jpg')])->assertSessionHasErrors('photo');
    expect($request->latestRun()?->corrections()->count())->toBe(0);
});

it('refuses to book a request with nothing priced', function () {
    config()->set('yardscope.extraction.live', true);
    Ai::fakeAgent(YardObservationAgent::class, [fn () => throw new ProviderConnectionException('timed out')]);
    $this->post('/requests', ['sentence' => 'Clean up the whole backyard please', 'photos' => demoUploads(2)]);
    $request = JobRequest::sole();

    $this->post(route('requests.book', $request))->assertSessionHasErrors('booking');
    $this->get(route('requests.booked', $request))->assertRedirect(route('requests.show', $request));
    expect($request->fresh()?->booked_at)->toBeNull();
});
