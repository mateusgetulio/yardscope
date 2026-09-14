<?php

namespace App\Intake;

use App\Models\JobRequest;
use App\Models\ObservationRun;
use App\Scoping\CorrectionApplier;
use App\Scoping\Data\Correction;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\InvalidCorrection;
use App\Scoping\Exceptions\NoRecordedObservation;
use App\Scoping\VisionExtractor;

final readonly class Analyzer
{
    public function __construct(
        private VisionExtractor $extractor,
        private ScopeAssembler $assembler,
        private CorrectionApplier $applier,
    ) {}

    /**
     * Runs the vision model once and stores what came back. A model failure is stored too, so the
     * request lands on a pro quote (rule R6) instead of an error page.
     *
     * @throws NoRecordedObservation
     */
    public function analyze(JobRequest $request): ObservationRun
    {
        $previous = $request->latestRun();
        $photos = $request->photoInputs();

        try {
            $run = $request->runs()->create(['photo_count' => count($photos), 'observation' => $this->extractor->extract($photos, $request->sentence)->toArray()]);
        } catch (ExtractionFailed $exception) {
            $run = $request->runs()->create(['photo_count' => count($photos), 'failure' => $exception->getMessage()]);
        }

        if ($previous !== null && $run->observation !== null) {
            $this->carry($request, $previous, $run);
        }

        $request->update(['readiness' => $this->assembler->assemble($request)->scope->readiness->value]);

        return $run;
    }

    /**
     * Corrections made on the previous run are applied to the new observation where a line for
     * the same service and section still exists. The domain re-checks each one, so a correction
     * that no longer fits, or that the model now agrees with, stays in the old run's history
     * instead of being forced onto the new scope.
     */
    private function carry(JobRequest $request, ObservationRun $previous, ObservationRun $run): void
    {
        $before = $this->assembler->assembleRun($request, $previous)->scope;
        $scope = $this->assembler->assembleRun($request, $run, withCorrections: false)->scope;

        foreach ($previous->corrections as $record) {
            $target = $this->sameLine($before->line($record->line_id), $scope);

            if ($target === null) {
                continue;
            }

            try {
                $applied = $this->applier->apply($scope, new Correction($target->id, $record->field, '', $record->customer_value, $record->reason));
            } catch (InvalidCorrection) {
                continue;
            }

            $carried = $applied->line($target->id)?->lastCorrection();

            if ($carried === null || $carried->modelValue === $carried->customerValue) {
                continue;
            }

            $scope = $applied;
            $run->corrections()->create([
                'line_id' => $target->id,
                'field' => $carried->field,
                'model_value' => $carried->modelValue,
                'customer_value' => $carried->customerValue,
                'reason' => $carried->reason,
                'source' => 'carried',
            ]);
        }
    }

    private function sameLine(?ScopeLine $line, JobScope $scope): ?ScopeLine
    {
        if ($line === null) {
            return null;
        }

        foreach ($scope->lines as $candidate) {
            if ($candidate->type === $line->type && $candidate->section === $line->section) {
                return $candidate;
            }
        }

        return null;
    }
}
