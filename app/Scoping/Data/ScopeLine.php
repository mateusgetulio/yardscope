<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\CorrectionField;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\ValueOrigin;

final readonly class ScopeLine
{
    /**
     * @param  list<ReadinessCheck>  $checks
     * @param  list<Evidence>  $supportingEvidence
     * @param  list<Evidence>  $evidence
     * @param  list<Correction>  $corrections
     */
    public function __construct(
        public string $id,
        public ServiceType $type,
        public ?Section $section,
        public LineValues $observed,
        public LineValues $current,
        public LineDisposition $disposition,
        public array $checks,
        public ?PhotoRequest $photoRequest,
        public ?string $note,
        public ?Evidence $countingEvidence,
        public array $supportingEvidence,
        public array $evidence,
        public ?string $uncertain,
        public bool $requested,
        public array $corrections = [],
    ) {}

    public function isPriceable(): bool
    {
        return $this->disposition === LineDisposition::Priceable;
    }

    public function isPlaceholder(): bool
    {
        return $this->section === null;
    }

    public function origin(): ValueOrigin
    {
        return array_any($this->corrections, fn (Correction $correction): bool => $correction->field->changesValue())
            ? ValueOrigin::CustomerCorrected
            : ValueOrigin::AiObserved;
    }

    public function lastCorrection(): ?Correction
    {
        return $this->corrections === [] ? null : $this->corrections[count($this->corrections) - 1];
    }

    public function wasRemoved(): bool
    {
        return $this->lastCorrection()?->field === CorrectionField::Removed;
    }

    /**
     * @param  list<ReadinessCheck>  $checks
     */
    public function corrected(LineValues $current, LineDisposition $disposition, array $checks, ?PhotoRequest $photoRequest, ?string $note, Correction $correction): self
    {
        return new self(
            $this->id, $this->type, $this->section, $this->observed, $current, $disposition, $checks, $photoRequest, $note,
            $this->countingEvidence, $this->supportingEvidence, $this->evidence, $this->uncertain, $this->requested, [...$this->corrections, $correction],
        );
    }

    public function passedChecks(): int
    {
        return count(array_filter($this->checks, fn (ReadinessCheck $check): bool => $check->passed));
    }
}
