<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\CorrectionField;

final readonly class Correction
{
    public function __construct(
        public string $lineId,
        public CorrectionField $field,
        public string $modelValue,
        public string $customerValue,
        public ?string $reason,
    ) {}

    public function withModelValue(string $modelValue): self
    {
        return new self($this->lineId, $this->field, $modelValue, $this->customerValue, $this->reason);
    }
}
