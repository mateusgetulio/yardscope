<?php

namespace App\Scoping\Data;

final readonly class RejectedLine
{
    public function __construct(
        public string $type,
        public string $reason,
    ) {}
}
