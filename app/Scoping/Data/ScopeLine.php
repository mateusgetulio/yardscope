<?php

namespace App\Scoping\Data;

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
     */
    public function __construct(
        public string $id,
        public ServiceType $type,
        public Section $section,
        public LineValues $observed,
        public LineValues $current,
        public LineDisposition $disposition,
        public LineDisposition $gated,
        public array $checks,
        public ?PhotoRequest $photoRequest,
        public ?string $note,
        public ?Evidence $countingEvidence,
        public array $supportingEvidence,
        public array $evidence,
        public ?string $uncertain,
        public bool $requested,
        public ?Correction $correction = null,
    ) {}

    public function isPriceable(): bool
    {
        return $this->disposition === LineDisposition::Priceable;
    }

    public function origin(): ValueOrigin
    {
        return $this->correction === null ? ValueOrigin::AiObserved : ValueOrigin::CustomerCorrected;
    }

    public function passedChecks(): int
    {
        return count(array_filter($this->checks, fn (ReadinessCheck $check): bool => $check->passed));
    }
}
