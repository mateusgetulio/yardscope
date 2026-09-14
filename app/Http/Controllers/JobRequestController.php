<?php

namespace App\Http\Controllers;

use App\Http\Presenters\ScopePresenter;
use App\Http\Requests\StoreJobRequest;
use App\Intake\Analyzer;
use App\Intake\PhotoStore;
use App\Intake\ScopeAssembler;
use App\Models\JobRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class JobRequestController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('request', [
            'profile' => config()->array('yardscope.demo_profile'),
            'liveMode' => config()->boolean('yardscope.extraction.live'),
        ]);
    }

    public function store(StoreJobRequest $request, PhotoStore $photos, Analyzer $analyzer): RedirectResponse
    {
        $jobRequest = JobRequest::create([
            'sentence' => $request->string('sentence')->trim()->toString(),
            'profile' => config()->array('yardscope.demo_profile'),
            'photos' => [],
        ]);

        try {
            $stored = [];

            foreach (array_values($request->file('photos')) as $index => $upload) {
                $stored[] = $photos->store($upload, $jobRequest->id, $index + 1);
            }

            $jobRequest->update(['photos' => $stored]);
            $analyzer->analyze($jobRequest);
        } catch (RuntimeException $exception) {
            // Covers an unreadable photo and, in fixture mode, a photo set with no recording.
            $jobRequest->delete();
            Storage::disk('local')->deleteDirectory("requests/{$jobRequest->id}");

            return back()->withInput()->withErrors(['photos' => $exception->getMessage()]);
        }

        return redirect()->route('requests.show', $jobRequest);
    }

    public function show(JobRequest $jobRequest, ScopeAssembler $assembler, ScopePresenter $presenter): Response
    {
        return Inertia::render('result', ['request' => $presenter->present($jobRequest, $assembler->assemble($jobRequest))]);
    }
}
