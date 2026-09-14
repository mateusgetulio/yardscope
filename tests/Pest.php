<?php

use App\Scoping\Data\Observation;
use App\Scoping\Data\PropertyProfile;
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
