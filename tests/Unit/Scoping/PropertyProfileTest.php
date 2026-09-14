<?php

use App\Scoping\Data\PropertyProfile;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\SectionSize;
use App\Scoping\Exceptions\InvalidPropertyProfile;

it('gives the size of each section', function () {
    expect(workedProfile()->sizeOf(Section::Backyard))->toBe(SectionSize::Medium)
        ->and(workedProfile()->sizeOf(Section::FrontYard))->toBe(SectionSize::Small);
});

it('rejects an invalid profile', function (array $data, string $message) {
    expect(fn () => PropertyProfile::fromArray($data))->toThrow(InvalidPropertyProfile::class, $message);
})->with([
    'empty' => [[], 'The property profile has no front yard.'],
    'missing section' => [['backyard' => 'small', 'front_yard' => 'small'], 'The property profile has no side yard.'],
    'unknown section' => [['roof' => 'small', 'backyard' => 'small', 'front_yard' => 'small', 'side_yard' => 'small'], 'Unknown section [roof] in the property profile.'],
    'unknown size' => [['backyard' => 'huge'], 'Section [backyard] needs a size of small, medium or large.'],
]);
