<?php

use App\Scoping\Data\PropertyProfile;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\SectionSize;
use App\Scoping\Exceptions\InvalidPropertyProfile;

it('gives the size of each section', function () {
    $profile = workedProfile();

    expect($profile->sizeOf(Section::Backyard))->toBe(SectionSize::Medium)
        ->and($profile->has(Section::FrontYard))->toBeTrue();
});

it('rejects an invalid profile', function (array $data, string $message) {
    expect(fn () => PropertyProfile::fromArray($data))->toThrow(InvalidPropertyProfile::class, $message);
})->with([
    'empty' => [[], 'A property profile needs at least one section.'],
    'unknown section' => [['roof' => 'small'], 'Unknown section [roof] in the property profile.'],
    'unknown size' => [['backyard' => 'huge'], 'Section [backyard] needs a size of small, medium or large.'],
]);

it('names a missing section', function () {
    expect(fn () => PropertyProfile::fromArray(['backyard' => 'small'])->sizeOf(Section::SideYard))
        ->toThrow(InvalidPropertyProfile::class, 'The property profile has no side yard.');
});
