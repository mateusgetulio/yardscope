<?php

namespace App\Scoping;

use App\Scoping\Data\BriefLine;
use App\Scoping\Data\Estimate;
use App\Scoping\Data\Evidence;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\ProBrief;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\ValueOrigin;

/**
 * Builds the pre-visit brief from the scope alone. Nothing is generated: every line, count,
 * note and question below is copied from a scope line, a photo request, a hazard or the access
 * note, so the brief cannot mention anything the scope does not contain (INV-7).
 */
final readonly class ProBriefBuilder
{
    public function build(JobScope $scope, ?Estimate $estimate): ProBrief
    {
        return new ProBrief(
            $scope->readiness,
            array_map(fn (ScopeLine $line): BriefLine => $this->line($line), $scope->lines),
            $this->photoNotes($scope),
            $this->accessNotes($scope),
            $this->openQuestions($scope),
            $estimate,
        );
    }

    private function line(ScopeLine $line): BriefLine
    {
        $corrected = $line->origin() === ValueOrigin::CustomerCorrected;

        return new BriefLine(
            $line->id,
            $line->type,
            $line->section,
            $line->disposition,
            $line->current,
            $line->origin(),
            $corrected ? $line->observed : null,
            $line->lastCorrection()?->reason,
            $line->wasRemoved(),
            array_values(array_filter([$line->countingEvidence, ...$line->supportingEvidence, ...$line->evidence])),
            $line->note,
        );
    }

    /**
     * @return array<int, list<string>>
     */
    private function photoNotes(JobScope $scope): array
    {
        $notes = [];

        foreach ($scope->photos as $photo) {
            $notes[$photo->photo] = [];
        }

        $add = function (Evidence $evidence, string $prefix) use (&$notes): void {
            $notes[$evidence->photo][] = "{$prefix}: {$evidence->note}";
        };

        foreach ($scope->lines as $line) {
            if ($line->disposition === LineDisposition::Rejected && ! $line->wasRemoved()) {
                continue;
            }

            foreach (array_filter([$line->countingEvidence, ...$line->supportingEvidence, ...$line->evidence]) as $evidence) {
                $add($evidence, $line->type->label());
            }
        }

        foreach ($scope->access->evidence as $evidence) {
            $add($evidence, 'Access');
        }

        foreach ($scope->hazards as $hazard) {
            foreach ($hazard->evidence as $evidence) {
                $add($evidence, 'Hazard');
            }
        }

        return $notes;
    }

    /**
     * @return list<string>
     */
    private function accessNotes(JobScope $scope): array
    {
        return $scope->access->narrowGatePossible
            ? ['The side gate may be narrower than a mower deck. Confirm the width before bringing equipment.']
            : [];
    }

    /**
     * @return list<string>
     */
    private function openQuestions(JobScope $scope): array
    {
        $questions = [];

        foreach ($scope->lines as $line) {
            if ($line->uncertain !== null && $line->disposition !== LineDisposition::Rejected) {
                $questions[] = "{$line->type->label()}: {$line->uncertain}";
            }

            if ($line->photoRequest !== null && $line->disposition === LineDisposition::NeedsPhotos) {
                $questions[] = "{$line->type->label()}: {$line->photoRequest->message}";
            }
        }

        foreach ($scope->hazards as $hazard) {
            $questions[] = "Hazard in the {$hazard->section->label()}: {$hazard->note}";
        }

        if ($scope->requestNote !== null) {
            $questions[] = "From the analysis: {$scope->requestNote}";
        }

        return $questions;
    }
}
