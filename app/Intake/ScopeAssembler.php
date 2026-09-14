<?php

namespace App\Intake;

use App\Models\JobRequest;
use App\Models\ObservationRun;
use App\Scoping\CorrectionApplier;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\Observation;
use App\Scoping\Exceptions\InvalidCorrection;
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
        return $this->assembleRun($request, $request->latestRun());
    }

    public function assembleRun(JobRequest $request, ?ObservationRun $run, bool $withCorrections = true): AssembledScope
    {
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
        $skipped = [];

        foreach ($withCorrections ? $run->corrections : [] as $record) {
            // A stored correction the domain no longer accepts (a rule changed, or two customers
            // raced) is skipped and reported rather than taking the whole request down.
            try {
                $scope = $this->applier->apply($scope, $record->toCorrection());
            } catch (InvalidCorrection) {
                $skipped[] = $record;
            }
        }

        return new AssembledScope($scope, $this->pricer->estimate($scope), $run, $observation->unsupportedRequests, $skipped);
    }
}
