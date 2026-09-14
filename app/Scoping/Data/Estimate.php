<?php

namespace App\Scoping\Data;

final readonly class Estimate
{
    /**
     * @param  list<LineEstimate>  $lines
     */
    public function __construct(
        public HoursRange $hours,
        public float $shownLowHours,
        public float $shownHighHours,
        public int $visitFeeCents,
        public int $priceCents,
        public array $lines,
    ) {}
}
