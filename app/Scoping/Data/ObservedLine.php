<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;

final readonly class ObservedLine
{
    /**
     * @param  list<Evidence>  $supportingEvidence
     * @param  list<Evidence>  $evidence
     */
    public function __construct(
        public ServiceType $type,
        public Section $section,
        public LineValues $values,
        public ?Evidence $countingEvidence,
        public array $supportingEvidence,
        public array $evidence,
        public ?string $uncertain,
    ) {}

}
