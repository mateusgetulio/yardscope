<?php

namespace App\Requests;

use App\Models\JobRequest;
use App\Models\ObservationRun;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\NoRecordedObservation;
use App\Scoping\VisionExtractor;

final readonly class Analyzer
{
    public function __construct(private VisionExtractor $extractor, private ScopeAssembler $assembler) {}

    /**
     * Runs the vision model once and stores what came back. A model failure is stored too, so the
     * request lands on a pro quote (rule R6) instead of an error page.
     *
     * @throws NoRecordedObservation
     */
    public function analyze(JobRequest $request): ObservationRun
    {
        $photos = $request->photoInputs();

        try {
            $run = $request->runs()->create(['photo_count' => count($photos), 'observation' => $this->extractor->extract($photos, $request->sentence)->toArray()]);
        } catch (ExtractionFailed $exception) {
            $run = $request->runs()->create(['photo_count' => count($photos), 'failure' => $exception->getMessage()]);
        }

        $request->update(['readiness' => $this->assembler->assemble($request)->scope->readiness->value]);

        return $run;
    }
}
