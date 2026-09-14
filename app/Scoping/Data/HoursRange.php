<?php

namespace App\Scoping\Data;

final readonly class HoursRange
{
    public function __construct(
        public float $low,
        public float $high,
    ) {}

    public function times(float $factor): self
    {
        return new self(round($this->low * $factor, 9), round($this->high * $factor, 9));
    }

    public function plus(self $other): self
    {
        return new self(round($this->low + $other->low, 9), round($this->high + $other->high, 9));
    }

    public function midpoint(): float
    {
        return round(($this->low + $this->high) / 2, 9);
    }
}
