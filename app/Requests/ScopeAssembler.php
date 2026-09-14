<?php

namespace App\Requests;

use App\Models\JobRequest;
use App\Scoping\CorrectionApplier;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\Observation;
use App\Scoping\Pricer;
use App\Scoping\ScopeBuilder;

final readonly class ScopeAssembler
{
    public function __construct(
        private ScopeBuilder $builder,
        private CorrectionApplier $applier,
        private Pricer $pricer,
    ) {}

    /**
     * The scope is never stored: it is rebuilt from the latest observation and the corrections
     * replayed in order, so what the customer sees is always what the domain computes (INV-4).
     */
    public function assemble(JobRequest $request): AssembledScope
    {
        $run = $request->latestRun();
        $profile = $request->propertyProfile();

        if ($run === null) {
            return new AssembledScope(JobScope::unreadable($profile, 'The photos have not been analyzed yet.'), null, null);
        }

        if ($run->observation === null) {
            // The raw failure stays on the run for the pro; the customer gets one plain sentence.
            return new AssembledScope(JobScope::unreadable($profile, 'We could not read these photos automatically. A pro will quote this job on site.'), null, $run);
        }

        $observation = Observation::fromArray($run->observation);
        $scope = $this->builder->build($observation, $profile);

        foreach ($run->corrections as $record) {
            $scope = $this->applier->apply($scope, $record->toCorrection());
        }

        return new AssembledScope($scope, $this->pricer->estimate($scope), $run, $observation->unsupportedRequests);
    }
}
