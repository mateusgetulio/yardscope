<?php

namespace App\Scoping;

use App\Scoping\Data\Correction;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\Observation;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use App\Scoping\Exceptions\InvalidCorrection;

final readonly class CorrectionApplier
{
    public const FIELDS = ['quantity', 'size', 'severity', 'removed', 'added'];

    public function __construct(private ReadinessGate $gate = new ReadinessGate) {}

    public function apply(JobScope $scope, Correction $correction): JobScope
    {
        $line = $scope->line($correction->lineId)
            ?? throw new InvalidCorrection("There is no line [{$correction->lineId}] to correct.");

        $corrected = match ($correction->field) {
            'removed' => $this->remove($line, $correction),
            'added' => $this->add($line, $scope, $correction),
            'quantity', 'size', 'severity' => $this->change($line, $scope, $correction),
            default => throw new InvalidCorrection("Unknown correction field [{$correction->field}]."),
        };

        return $scope->withLines(array_map(fn (ScopeLine $each): ScopeLine => $each->id === $line->id ? $corrected : $each, $scope->lines));
    }

    private function remove(ScopeLine $line, Correction $correction): ScopeLine
    {
        if ($line->disposition === LineDisposition::Rejected) {
            throw new InvalidCorrection("Line [{$line->id}] is already out of the job.");
        }

        return $line->corrected($line->current, LineDisposition::Rejected, $line->checks, 'Removed by the customer.', $correction);
    }

    private function add(ScopeLine $line, JobScope $scope, Correction $correction): ScopeLine
    {
        if ($line->disposition !== LineDisposition::Suggested) {
            throw new InvalidCorrection("Line [{$line->id}] was not a suggestion.");
        }

        [$disposition, $checks, , $note] = $this->gate->regate($line, $line->current, $scope->hasHazardIn($line->section));

        return $line->corrected($line->current, $disposition, $checks, $note, $correction);
    }

    private function change(ScopeLine $line, JobScope $scope, Correction $correction): ScopeLine
    {
        if (in_array($line->disposition, [LineDisposition::Rejected, LineDisposition::Suggested], true)) {
            throw new InvalidCorrection("Line [{$line->id}] has to be added to the job before it can be corrected.");
        }

        if ($line->observed->quantity === null && $line->observed->severity === null) {
            throw new InvalidCorrection("Line [{$line->id}] has nothing to correct until a photo shows it.");
        }

        $values = $this->valuesAfter($line, $correction);
        [$disposition, $checks, , $note] = $this->gate->regate($line, $values, $scope->hasHazardIn($line->section));

        return $line->corrected($values, $disposition, $checks, $note, $correction);
    }

    private function valuesAfter(ScopeLine $line, Correction $correction): LineValues
    {
        $value = $correction->customerValue;
        $counted = $line->type->isCounted();
        $usesSize = in_array($line->type, [ServiceType::ShrubTrimming, ServiceType::BranchRemoval], true);

        if ($correction->field === 'quantity') {
            if (! $counted || ! ctype_digit($value) || (int) $value < 1 || (int) $value > Observation::MAX_QUANTITY) {
                throw new InvalidCorrection("A {$line->type->label()} count must be between 1 and ".Observation::MAX_QUANTITY.'.');
            }

            return $line->current->with(quantity: (int) $value);
        }

        if ($correction->field === 'size') {
            $size = Size::tryFrom($value);

            if (! $usesSize || $size === null) {
                throw new InvalidCorrection($usesSize
                    ? "{$line->type->label()} takes a size of small, medium or large."
                    : "{$line->type->label()} takes a severity of light, moderate or heavy.");
            }

            return $line->current->with(size: $size);
        }

        $severity = Severity::tryFrom($value);

        if ($usesSize || $severity === null) {
            throw new InvalidCorrection($usesSize
                ? "{$line->type->label()} takes a size of small, medium or large."
                : "{$line->type->label()} takes a severity of light, moderate or heavy.");
        }

        return $line->current->with(severity: $severity);
    }
}
