<?php

namespace App\Scoping\Data;

final readonly class Correction
{
    public function __construct(
        public string $lineId,
        public string $field,
        public string $modelValue,
        public string $customerValue,
        public ?string $reason,
    ) {}
}
