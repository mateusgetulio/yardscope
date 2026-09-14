<?php

namespace App\Evals;

use App\Extraction\FixtureExtractor;
use App\Extraction\RecordsObservations;
use App\Scoping\Data\Observation;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\InvalidObservation;
use App\Scoping\Exceptions\NoRecordedObservation;
use App\Scoping\ScopeBuilder;

/**
 * Runs every labeled set through the pipeline and scores it. A live run records each model
 * answer as a fixture, so the same run can be replayed later without the model.
 */
final readonly class EvalRunner
{
    public function __construct(
        private ScopeBuilder $builder,
        private Scorer $scorer,
        private string $setsDirectory,
        private string $fixturesDirectory,
    ) {}

    /**
     * @return list<EvalSet>
     */
    public function sets(): array
    {
        $directories = glob($this->setsDirectory.'/*', GLOB_ONLYDIR) ?: [];
        sort($directories);

        return array_map(fn (string $directory): EvalSet => EvalSet::load($directory), $directories);
    }

    public function replay(EvalSet $set): SetScore
    {
        $fixtures = new FixtureExtractor($this->fixturesDirectory);

        try {
            $observation = $fixtures->extract($set->photos, $set->sentence);
        } catch (NoRecordedObservation $exception) {
            return $this->scorer->score($set, null, null, $exception->getMessage());
        }

        return $this->scored($set, $observation);
    }

    public function live(EvalSet $set, RecordsObservations $extractor): SetScore
    {
        try {
            $answer = $extractor->observe($set->photos, $set->sentence);
        } catch (ExtractionFailed $exception) {
            return $this->scorer->score($set, null, null, $exception->getMessage());
        }

        $this->record($set, $answer);

        try {
            $observation = Observation::fromArray($answer);
        } catch (InvalidObservation $exception) {
            return $this->scorer->score($set, null, null, $exception->getMessage());
        }

        return $this->scored($set, $observation);
    }

    private function scored(EvalSet $set, Observation $observation): SetScore
    {
        return $this->scorer->score($set, $observation, $this->builder->build($observation, $set->profile), null);
    }

    /**
     * @param  array<string, mixed>  $answer
     */
    private function record(EvalSet $set, array $answer): void
    {
        if (! is_dir($this->fixturesDirectory)) {
            mkdir($this->fixturesDirectory, 0755, true);
        }

        file_put_contents((new FixtureExtractor($this->fixturesDirectory))->pathFor($set->photos, $set->sentence), json_encode([
            'set' => $set->slug,
            'sentence' => $set->sentence,
            'photos' => array_map(fn ($photo): string => basename($photo->path), $set->photos),
            'recorded_at' => date('c'),
            'observation' => $answer,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
