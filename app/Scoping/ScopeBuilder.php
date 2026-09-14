<?php

namespace App\Scoping;

use App\Scoping\Data\JobScope;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\Observation;
use App\Scoping\Data\ObservedLine;
use App\Scoping\Data\PhotoRequest;
use App\Scoping\Data\PropertyProfile;
use App\Scoping\Data\ReadinessCheck;
use App\Scoping\Data\ReadinessRollup;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\ReadinessRule;
use App\Scoping\Enums\ServiceType;

final readonly class ScopeBuilder
{
    public function __construct(private ReadinessGate $gate = new ReadinessGate) {}

    public function build(Observation $observation, PropertyProfile $profile): JobScope
    {
        $lines = [];

        foreach ($observation->lines as $index => $observed) {
            $lines[] = $this->fromObserved($observed, $index + 1, $observation);
        }

        foreach ($observation->requestedInSentence as $type) {
            if (! in_array($type, $observation->observedTypes(), true)) {
                $lines[] = $this->placeholder($type, count($lines) + 1, $observation->wasRejected($type));
            }
        }

        return new JobScope(
            profile: $profile,
            photos: $observation->photos,
            lines: $lines,
            rejected: $observation->rejected,
            access: $observation->access,
            hazards: $observation->hazards,
            readiness: ReadinessRollup::from($lines),
            requestNote: $observation->modelNotes,
        );
    }

    private function fromObserved(ObservedLine $observed, int $number, Observation $observation): ScopeLine
    {
        [$gated, $checks, $request, $note] = $this->gate->evaluate($observed->type, $observed->section, $observed->values, $observed->uncertain, $observation);
        $requested = in_array($observed->type, $observation->requestedInSentence, true);

        return new ScopeLine(
            id: "line-{$number}",
            type: $observed->type,
            section: $observed->section,
            observed: $observed->values,
            current: $observed->values,
            disposition: $requested ? $gated : LineDisposition::Suggested,
            checks: $checks,
            photoRequest: $requested ? $request : null,
            note: $note,
            countingEvidence: $observed->countingEvidence,
            supportingEvidence: $observed->supportingEvidence,
            evidence: $observed->evidence,
            uncertain: $observed->uncertain,
            requested: $requested,
        );
    }

    /**
     * Requested work no photo shows (rule R3). It deliberately has no evidence, so R4 does not apply.
     */
    private function placeholder(ServiceType $type, int $number, bool $rejected): ScopeLine
    {
        $empty = new LineValues(null, null, null);
        $message = $rejected
            ? "We could not read the {$type->label()} from these photos. Add one clear photo of it."
            : "You asked for {$type->label()}, but no photo shows it. Add one photo of that area.";

        return new ScopeLine(
            id: "line-{$number}",
            type: $type,
            section: null,
            observed: $empty,
            current: $empty,
            disposition: LineDisposition::NeedsPhotos,
            checks: [new ReadinessCheck(ReadinessRule::RequestedUnseen, false, $message)],
            photoRequest: new PhotoRequest($message, null, $type),
            note: null,
            countingEvidence: null,
            supportingEvidence: [],
            evidence: [],
            uncertain: null,
            requested: true,
        );
    }
}
