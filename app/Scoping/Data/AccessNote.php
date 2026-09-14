<?php

namespace App\Scoping\Data;

final readonly class AccessNote
{
    /**
     * @param  list<Evidence>  $evidence
     */
    public function __construct(
        public bool $narrowGatePossible,
        public array $evidence,
    ) {}
}
