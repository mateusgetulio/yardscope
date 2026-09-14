<?php

namespace App\Scoping\Data;

final readonly class PhotoInput
{
    public function __construct(
        public int $number,
        public string $path,
        public ?string $mimeType = null,
    ) {}
}
