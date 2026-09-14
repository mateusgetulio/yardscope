<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;

final readonly class LineValues
{
    public function __construct(
        public ?int $quantity,
        public ?Size $size,
        public ?Severity $severity,
    ) {}

    public function with(?int $quantity = null, ?Size $size = null, ?Severity $severity = null): self
    {
        return new self($quantity ?? $this->quantity, $size ?? $this->size, $severity ?? $this->severity);
    }
}
