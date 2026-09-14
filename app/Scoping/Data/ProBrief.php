<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\RequestReadiness;

final readonly class ProBrief
{
    /**
     * @param  list<BriefLine>  $lines
     * @param  array<int, list<string>>  $photoNotes  evidence notes per photo number
     * @param  list<string>  $accessNotes
     * @param  list<string>  $openQuestions
     */
    public function __construct(
        public RequestReadiness $readiness,
        public array $lines,
        public array $photoNotes,
        public array $accessNotes,
        public array $openQuestions,
        public ?Estimate $estimate,
    ) {}
}
