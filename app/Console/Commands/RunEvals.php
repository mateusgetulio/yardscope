<?php

namespace App\Console\Commands;

use App\Evals\EvalRunner;
use App\Evals\Metrics;
use App\Evals\Scorer;
use App\Evals\SetScore;
use App\Extraction\RecordsObservations;
use App\Scoping\ScopeBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

#[Signature('yardscope:eval {--live : Run the labeled sets through the configured live extractor and record the answers} {--fixtures : Replay the recorded answers} {--expect= : Fail when the metrics, readiness or dispositions drift from this results file ("latest" for the newest one)}')]
#[Description('Score the labeled photo sets and write the results file')]
class RunEvals extends Command
{
    public function handle(ScopeBuilder $builder, Scorer $scorer): int
    {
        if ($this->option('live') === $this->option('fixtures')) {
            $this->error('Choose one of --live or --fixtures.');

            return self::INVALID;
        }

        $runner = new EvalRunner($builder, $scorer, self::path('yardscope.evals.sets'), self::path('yardscope.evals.fixtures'));

        try {
            $sets = $runner->sets();
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($sets === []) {
            $this->error('No labeled sets under '.self::path('yardscope.evals.sets').'.');

            return self::FAILURE;
        }

        $live = (bool) $this->option('live');
        $extractor = $live ? $this->laravel->make(RecordsObservations::class) : null;
        $scores = [];

        foreach ($sets as $set) {
            $score = $live && $extractor !== null ? $runner->live($set, $extractor) : $runner->replay($set);
            $scores[] = $score;
            $this->line(sprintf('%-32s %s', $set->slug, $this->summary($score)));
        }

        $metrics = Metrics::from($scores);
        $results = self::path('yardscope.evals.results');

        if (! is_dir($results)) {
            mkdir($results, 0755, true);
        }

        $drift = $this->option('expect') === null ? [] : $this->drift((string) $this->option('expect'), $results, $metrics, $scores);

        $file = $results.'/'.date('Y-m-d-His').'.json';
        file_put_contents($file, json_encode([
            'ran_at' => date('c'),
            'mode' => $live ? 'live' : 'fixtures',
            'driver' => $live ? config()->string('yardscope.extraction.driver') : 'fixtures',
            'metrics' => $metrics,
            'sets' => array_map(fn (SetScore $score): array => $score->toArray(), $scores),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION));

        $this->newLine();

        foreach ($metrics as $name => $value) {
            $this->line(sprintf('%-28s %s', $name, $value === null ? 'n/a' : (string) $value));
        }

        $this->newLine();
        $this->info("Wrote {$file}");

        foreach ($drift as $line) {
            $this->error($line);
        }

        return $drift !== [] || array_any($scores, fn (SetScore $score): bool => ! $score->schemaValid) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Differences from a committed results file, so a change in the gate, the pricing or the
     * scorer cannot pass unnoticed on the recorded answers.
     *
     * @param  array<string, mixed>  $metrics
     * @param  list<SetScore>  $scores
     * @return list<string>
     */
    private function drift(string $expect, string $results, array $metrics, array $scores): array
    {
        if ($expect === 'latest') {
            $files = glob($results.'/*.json') ?: [];
            rsort($files);
            $expect = $files[0] ?? '';
        }

        if (! is_file($expect)) {
            return ["No results file to compare with at [{$expect}]."];
        }

        $reference = json_decode((string) file_get_contents($expect), true);

        if (! is_array($reference)) {
            return ["The results file at [{$expect}] could not be read."];
        }

        $drift = [];

        foreach ($reference['metrics'] ?? [] as $name => $value) {
            if (($metrics[$name] ?? null) !== $value) {
                $drift[] = "{$name}: ".json_encode($metrics[$name] ?? null).' now, '.json_encode($value).' in '.basename($expect).'.';
            }
        }

        $expectedSets = [];

        foreach ($reference['sets'] ?? [] as $set) {
            $expectedSets[$set['slug']] = $set;
        }

        foreach ($scores as $score) {
            $before = $expectedSets[$score->slug] ?? null;

            if ($before === null) {
                $drift[] = "{$score->slug}: not in ".basename($expect).'.';

                continue;
            }

            $now = $score->toArray();

            foreach (['observed_readiness', 'dispositions', 'hallucinated', 'observed_services'] as $field) {
                if (($before[$field] ?? null) !== $now[$field]) {
                    $drift[] = "{$score->slug} {$field}: ".json_encode($now[$field]).' now, '.json_encode($before[$field] ?? null).' before.';
                }
            }
        }

        return $drift;
    }

    private function summary(SetScore $score): string
    {
        if (! $score->schemaValid) {
            return 'no observation: '.$score->failure;
        }

        $parts = [$score->observedReadiness.($score->readinessCorrect() === false ? " (expected {$score->expectedReadiness})" : '')];

        if ($score->hallucinated !== []) {
            $parts[] = 'hallucinated '.implode(', ', $score->hallucinated);
        }

        if ($score->duplicateLines > 0) {
            $parts[] = "{$score->duplicateLines} duplicate line".($score->duplicateLines === 1 ? '' : 's');
        }

        $missed = array_values(array_diff($score->expectedServices, $score->observedServices));

        if ($missed !== []) {
            $parts[] = 'missed '.implode(', ', $missed);
        }

        foreach ($score->counts as $count) {
            if (! $count['exact']) {
                $parts[] = "{$count['service']} counted ".($count['observed'] ?? 'nothing')." for {$count['expected']}";
            }
        }

        return implode('; ', $parts);
    }

    private static function path(string $key): string
    {
        $configured = config()->string($key);

        return str_starts_with($configured, '/') ? $configured : base_path($configured);
    }
}
