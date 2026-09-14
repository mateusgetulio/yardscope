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

#[Signature('yardscope:eval {--live : Run the labeled sets through the configured live extractor and record the answers} {--fixtures : Replay the recorded answers}')]
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

        $file = $results.'/'.date('Y-m-d').'.json';
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

        return array_any($scores, fn (SetScore $score): bool => ! $score->schemaValid) ? self::FAILURE : self::SUCCESS;
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
