<?php

namespace App\Scoping;

use App\Scoping\Data\Correction;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\Observation;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\CorrectionField;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use App\Scoping\Exceptions\InvalidCorrection;

final readonly class CorrectionApplier
{
    public function __construct(private ReadinessGate $gate = new ReadinessGate) {}

    public function apply(JobScope $scope, Correction $correction): JobScope
    {
        $line = $scope->line($correction->lineId)
            ?? throw new InvalidCorrection("There is no line [{$correction->lineId}] to correct.");

        $corrected = match ($correction->field) {
            CorrectionField::Removed => $this->remove($line, $correction),
            CorrectionField::Added => $this->add($line, $scope, $correction),
            CorrectionField::Quantity, CorrectionField::Size, CorrectionField::Severity => $this->change($line, $scope, $correction),
        };

        return $scope->withLines(array_map(fn (ScopeLine $each): ScopeLine => $each->id === $line->id ? $corrected : $each, $scope->lines));
    }

    private function remove(ScopeLine $line, Correction $correction): ScopeLine
    {
        if ($line->disposition === LineDisposition::Rejected) {
            throw new InvalidCorrection("Line [{$line->id}] is already out of the job.");
        }

        return $line->corrected($line->current, LineDisposition::Rejected, $line->checks, null, 'Removed by the customer.', $correction->withModelValue($line->disposition->value));
    }

    private function add(ScopeLine $line, JobScope $scope, Correction $correction): ScopeLine
    {
        if ($line->disposition !== LineDisposition::Suggested && ! $line->wasRemoved()) {
            throw new InvalidCorrection("Line [{$line->id}] was not a suggestion.");
        }

        [$disposition, $checks, $request, $note] = $this->gate->regate($line, $line->current, $scope->hasHazardIn($this->sectionOf($line)));

        return $line->corrected($line->current, $disposition, $checks, $request, $note, $correction->withModelValue($line->disposition->value));
    }

    private function change(ScopeLine $line, JobScope $scope, Correction $correction): ScopeLine
    {
        if (in_array($line->disposition, [LineDisposition::Rejected, LineDisposition::Suggested], true)) {
            throw new InvalidCorrection("Line [{$line->id}] has to be added to the job before it can be corrected.");
        }

        if ($line->isPlaceholder()) {
            throw new InvalidCorrection("Line [{$line->id}] has nothing to correct until a photo shows it.");
        }

        $modelValue = $this->currentValue($line, $correction->field);

        if ($correction->modelValue !== '' && $correction->modelValue !== $modelValue) {
            throw new InvalidCorrection("Line [{$line->id}] currently has {$correction->field->value} [{$modelValue}], not [{$correction->modelValue}].");
        }

        $values = $this->valuesAfter($line, $correction);
        [$disposition, $checks, $request, $note] = $this->gate->regate($line, $values, $scope->hasHazardIn($this->sectionOf($line)));

        return $line->corrected($values, $disposition, $checks, $request, $note, $correction->withModelValue($modelValue));
    }

    private function sectionOf(ScopeLine $line): Section
    {
        return $line->section ?? throw new InvalidCorrection("Line [{$line->id}] has nothing to correct until a photo shows it.");
    }

    private function currentValue(ScopeLine $line, CorrectionField $field): string
    {
        return match ($field) {
            CorrectionField::Quantity => (string) $line->current->quantity,
            CorrectionField::Size => $line->current->size->value ?? '',
            CorrectionField::Severity => $line->current->severity->value ?? '',
            default => '',
        };
    }

    private function valuesAfter(ScopeLine $line, Correction $correction): LineValues
    {
        $value = $correction->customerValue;
        $usesSize = in_array($line->type, [ServiceType::ShrubTrimming, ServiceType::BranchRemoval], true);
        $sizeMessage = "{$line->type->label()} takes a size of small, medium or large.";
        $severityMessage = "{$line->type->label()} takes a severity of light, moderate or heavy.";

        if ($correction->field === CorrectionField::Quantity) {
            if (! $line->type->isCounted() || ! ctype_digit($value) || (int) $value < 1 || (int) $value > Observation::MAX_QUANTITY) {
                throw new InvalidCorrection("A {$line->type->label()} count must be between 1 and ".Observation::MAX_QUANTITY.'.');
            }

            return $line->current->with(quantity: (int) $value);
        }

        if ($correction->field === CorrectionField::Size) {
            $size = Size::tryFrom($value);

            if (! $usesSize || $size === null) {
                throw new InvalidCorrection($usesSize ? $sizeMessage : $severityMessage);
            }

            return $line->current->with(size: $size);
        }

        $severity = Severity::tryFrom($value);

        if ($usesSize || $severity === null) {
            throw new InvalidCorrection($usesSize ? $sizeMessage : $severityMessage);
        }

        return $line->current->with(severity: $severity);
    }
}
