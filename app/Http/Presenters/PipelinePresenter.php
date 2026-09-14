<?php

namespace App\Http\Presenters;

use App\Intake\AssembledScope;
use App\Models\CorrectionRecord;
use App\Models\JobRequest;
use App\Models\ObservationRun;
use App\Scoping\Data\LineEstimate;
use App\Scoping\Data\ReadinessCheck;
use App\Scoping\Data\RejectedLine;
use App\Scoping\Data\ScopeLine;

/**
 * The actual values each stage of the pipeline produced for this request, for the diagnostic panel.
 */
final readonly class PipelinePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(JobRequest $request, AssembledScope $assembled): array
    {
        $scope = $assembled->scope;
        $skipped = array_map(fn (CorrectionRecord $record): int => $record->id, $assembled->skippedCorrections);

        return [
            'photos' => $this->photos($request, $assembled->run),
            'extraction' => [
                'driver' => config()->string('yardscope.extraction.driver'),
                'runs' => $request->runs()->orderBy('id')->get()->map(fn (ObservationRun $run): array => [
                    'id' => $run->id,
                    'photoCount' => $run->photo_count,
                    'failure' => $run->failure,
                    'lines' => is_array($run->observation) ? count($run->observation['service_lines'] ?? []) : 0,
                    'at' => $run->created_at?->toIso8601String(),
                ])->all(),
            ],
            'validation' => [
                'rejected' => array_map(fn (RejectedLine $line): array => ['type' => $line->type, 'reason' => $line->reason], $scope->rejected),
                'unsupportedRequests' => $assembled->unsupportedRequests,
                'requestNote' => $scope->requestNote,
            ],
            'dispositions' => array_map(fn (ScopeLine $line): array => [
                'id' => $line->id,
                'label' => $line->type->label().($line->section === null ? '' : ', '.$line->section->label()),
                'disposition' => $line->disposition->value,
                'checks' => array_map(fn (ReadinessCheck $check): array => ['rule' => $check->rule->value, 'passed' => $check->passed, 'message' => $check->message], $line->checks),
            ], $scope->lines),
            'pricing' => $assembled->estimate === null ? null : [
                'lines' => array_map(fn (LineEstimate $line): array => ['id' => $line->lineId, 'lowHours' => $line->hours->low, 'highHours' => $line->hours->high, 'laborCents' => $line->laborCents], $assembled->estimate->lines),
                'lowHours' => $assembled->estimate->hours->low,
                'highHours' => $assembled->estimate->hours->high,
                'shownLowHours' => $assembled->estimate->shownLowHours,
                'shownHighHours' => $assembled->estimate->shownHighHours,
                'visitFeeCents' => $assembled->estimate->visitFeeCents,
                'priceCents' => $assembled->estimate->priceCents,
            ],
            'corrections' => $request->runs()->orderBy('id')->get()->flatMap(fn (ObservationRun $run) => $run->corrections->map(fn (CorrectionRecord $record): array => [
                'run' => $run->id,
                'lineId' => $record->line_id,
                'field' => $record->field->value,
                'modelValue' => $record->model_value,
                'customerValue' => $record->customer_value,
                'reason' => $record->reason,
                'source' => $record->source,
                'skipped' => in_array($record->id, $skipped, true),
                'current' => $run->id === $assembled->run?->id,
            ]))->values()->all(),
        ];
    }

    /**
     * Each stored photo with what the model said about it, or nothing when the run failed.
     *
     * @return list<array<string, mixed>>
     */
    private function photos(JobRequest $request, ?ObservationRun $run): array
    {
        $described = is_array($run?->observation) ? ($run->observation['photos'] ?? []) : [];
        $byNumber = [];

        foreach (is_array($described) ? $described : [] as $photo) {
            if (is_array($photo) && isset($photo['photo'])) {
                $byNumber[$photo['photo']] = $photo;
            }
        }

        return array_map(fn (array $photo): array => [
            'number' => $photo['number'],
            'view' => $byNumber[$photo['number']]['view'] ?? null,
            'sections' => $byNumber[$photo['number']]['sections'] ?? [],
            'usable' => $byNumber[$photo['number']]['usable'] ?? null,
        ], $request->photos);
    }
}
