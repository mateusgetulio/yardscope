<?php

use App\Scoping\Data\Estimate;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\Observation;
use App\Scoping\Data\PropertyProfile;
use App\Scoping\Data\RateCard;
use App\Scoping\Pricer;
use App\Scoping\ScopeBuilder;
use Tests\Support\RandomScopeFactory;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

const RANDOM_SCOPES = 1500;

pest()->extend(TestCase::class)->in('Feature');

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
