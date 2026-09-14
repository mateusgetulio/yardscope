<?php

/*
 * Helpers for feature tests that drive the customer flow through FixtureExtractor.
 */

use App\Extraction\FixtureExtractor;
use App\Intake\PhotoStore;
use App\Models\JobRequest;
use App\Scoping\Data\PhotoInput;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

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
