<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\ReadinessRule;

final readonly class ReadinessCheck
{
    public function __construct(
        public ReadinessRule $rule,
        public bool $passed,
        public string $message,
    ) {}
}
