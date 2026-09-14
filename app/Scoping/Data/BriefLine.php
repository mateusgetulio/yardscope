<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\ValueOrigin;

/**
 * One line of the pre-visit brief. Every value carries where it came from, and a corrected
 * value keeps the observed one next to it so the pro can see both (INV-7).
 */
final readonly class BriefLine
{
    /**
     * @param  list<Evidence>  $evidence
     */
    public function __construct(
        public string $lineId,
        public ServiceType $type,
        public ?Section $section,
        public LineDisposition $disposition,
        public LineValues $values,
        public ValueOrigin $origin,
        public ?LineValues $observed,
        public ?string $customerReason,
        public bool $removedByCustomer,
        public array $evidence,
        public ?string $note,
    ) {}
}
