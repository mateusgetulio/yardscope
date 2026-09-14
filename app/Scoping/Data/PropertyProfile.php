<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\Section;
use App\Scoping\Enums\SectionSize;
use App\Scoping\Exceptions\InvalidPropertyProfile;

final readonly class PropertyProfile
{
    /**
     * @param  array<string, SectionSize>  $sizes  keyed by section value
     */
    public function __construct(public array $sizes)
    {
        foreach ($sizes as $section => $size) {
            if (Section::tryFrom((string) $section) === null) {
                throw new InvalidPropertyProfile("Unknown section [{$section}] in the property profile.");
            }
        }

        foreach (Section::cases() as $section) {
            if (! isset($sizes[$section->value])) {
                throw new InvalidPropertyProfile("The property profile has no {$section->label()}.");
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $sizes = [];

        foreach ($data as $section => $size) {
            if (! is_string($size) || SectionSize::tryFrom($size) === null) {
                throw new InvalidPropertyProfile("Section [{$section}] needs a size of small, medium or large.");
            }

            $sizes[(string) $section] = SectionSize::from($size);
        }

        return new self($sizes);
    }

    public function sizeOf(Section $section): SectionSize
    {
        return $this->sizes[$section->value];
    }
}
