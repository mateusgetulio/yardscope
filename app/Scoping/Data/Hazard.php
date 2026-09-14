<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\Section;

final readonly class Hazard
{
    /**
     * @param  list<Evidence>  $evidence
     */
    public function __construct(
        public Section $section,
        public string $note,
        public array $evidence,
    ) {}
}
