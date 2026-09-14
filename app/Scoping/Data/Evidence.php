<?php

namespace App\Scoping\Data;

final readonly class Evidence
{
    public function __construct(
        public int $photo,
        public string $note,
    ) {}
}
