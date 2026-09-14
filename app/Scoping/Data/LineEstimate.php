<?php

namespace App\Scoping\Data;

final readonly class LineEstimate
{
    public function __construct(
        public string $lineId,
        public HoursRange $hours,
        public int $laborCents,
    ) {}
}
