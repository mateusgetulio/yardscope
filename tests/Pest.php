<?php

use App\Scoping\Data\Estimate;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoInput;
use App\Scoping\Data\PropertyProfile;
use App\Scoping\Data\RateCard;
use App\Scoping\Pricer;
use App\Scoping\ScopeBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RandomScopeFactory;
use Tests\TestCase;

require __DIR__.'/Support/Flow.php';

const RANDOM_SCOPES = 1500;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature');

/**
 * @return array<string, mixed>
 */
function workedExample(): array
{
    return json_decode((string) file_get_contents(__DIR__.'/fixtures/backyard-cleanup.json'), true);
}

function workedObservation(): Observation
{
    return Observation::fromArray(workedExample()['observation']);
}

function workedProfile(): PropertyProfile
{
    return PropertyProfile::fromArray(workedExample()['profile']);
}

/**
 * @return array<string, mixed>
 */
function shippedRates(): array
{
    return (require __DIR__.'/../config/yardscope.php')['rates'];
}

function pricer(array $overrides = []): Pricer
{
    return new Pricer(RateCard::fromArray(array_replace_recursive(shippedRates(), $overrides)));
}

function workedScope(): JobScope
{
    return (new ScopeBuilder)->build(workedObservation(), workedProfile());
}

/**
 * @return Generator<int, array{Observation, PropertyProfile, JobScope, ?Estimate}>
 */
function pricedRandomScopes(): Generator
{
    static $builder = null;
    static $pricer = null;
    $builder ??= new ScopeBuilder;
    $pricer ??= pricer();

    foreach (randomScopeSeeds() as $seed) {
        try {
            $factory = new RandomScopeFactory($seed);
            $observation = $factory->observation();
            $profile = $factory->profile();
            $scope = $builder->build($observation, $profile);
            $estimate = $pricer->estimate($scope);
        } catch (Throwable $exception) {
            throw new RuntimeException("Random scope seed {$seed} could not be built and priced. Rerun it with SCOPE_TEST_SEED={$seed}. {$exception->getMessage()}", previous: $exception);
        }

        yield $seed => [$observation, $profile, $scope, $estimate];
    }
}

/**
 * @return list<int>
 */
function randomScopeSeeds(): array
{
    $seed = getenv('SCOPE_TEST_SEED');

    if ($seed === false) {
        return range(1, RANDOM_SCOPES);
    }

    $parsed = filter_var($seed, FILTER_VALIDATE_INT);

    if ($parsed === false) {
        throw new InvalidArgumentException("SCOPE_TEST_SEED must be an integer, got [{$seed}].");
    }

    return [$parsed];
}

function invariantFailure(string $invariant, int $seed): string
{
    return "{$invariant} failed for random scope seed {$seed}. Rerun it with SCOPE_TEST_SEED={$seed} vendor/bin/pest --filter='{$invariant}\\b'";
}

/**
 * Tiny valid PNG files in a scratch directory, numbered from 1.
 *
 * @return list<PhotoInput>
 */
function scratchPhotos(int $count, string $directory): array
{
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==');
    $photos = [];

    foreach (range(1, $count) as $number) {
        $path = "{$directory}/photo-{$number}.png";
        file_put_contents($path, $png.str_repeat("\0", $number));
        $photos[] = new PhotoInput($number, $path, 'image/png');
    }

    return $photos;
}

function scratchDirectory(): string
{
    $directory = sys_get_temp_dir().'/yardscope-'.bin2hex(random_bytes(6));
    mkdir($directory);
    register_shutdown_function(function () use ($directory): void {
        array_map(unlink(...), glob("{$directory}/*") ?: []);
        @rmdir($directory);
    });

    return $directory;
}
