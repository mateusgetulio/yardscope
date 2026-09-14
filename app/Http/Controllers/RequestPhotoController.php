<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPhotoRequest;
use App\Intake\Analyzer;
use App\Intake\PhotoStore;
use App\Models\JobRequest;
use App\Scoping\Exceptions\NoRecordedObservation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RequestPhotoController extends Controller
{
    public function show(JobRequest $jobRequest, int $number): BinaryFileResponse
    {
        foreach ($jobRequest->photos as $photo) {
            if ($photo['number'] === $number) {
                return response()->file(Storage::disk('local')->path($photo['path']));
            }
        }

        abort(404);
    }

    /**
     * Adds one photo and analyzes the whole set again. The new run starts from the model's fresh
     * observation; corrections made on the previous run stay in its history and are not replayed,
     * because the lines the model sees may have changed.
     */
    public function store(AddPhotoRequest $request, JobRequest $jobRequest, PhotoStore $photos, Analyzer $analyzer): RedirectResponse
    {
        if ($jobRequest->isBooked()) {
            return back()->withErrors(['photo' => 'This job is booked. Send new photos to your pro.']);
        }

        if (count($jobRequest->photos) >= 4) {
            return back()->withErrors(['photo' => 'Up to four photos.']);
        }

        $before = $jobRequest->photos;
        $stored = $photos->store($request->file('photo'), $jobRequest->id, count($before) + 1);
        $jobRequest->update(['photos' => [...$before, $stored]]);

        try {
            $analyzer->analyze($jobRequest);
        } catch (NoRecordedObservation $exception) {
            $jobRequest->update(['photos' => $before]);
            Storage::disk('local')->delete($stored['path']);

            return back()->withErrors(['photo' => $exception->getMessage()]);
        }

        return redirect()->route('requests.show', $jobRequest);
    }
}
