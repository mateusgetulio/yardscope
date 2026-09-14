<?php

namespace App\Intake;

use App\Models\ObservationRun;
use App\Scoping\Data\Estimate;
use App\Scoping\Data\JobScope;

final readonly class AssembledScope
{
    /**
     * @param  list<string>  $unsupportedRequests
     */
    public function __construct(
        public JobScope $scope,
        public ?Estimate $estimate,
        public ?ObservationRun $run,
        public array $unsupportedRequests = [],
    ) {}
}
