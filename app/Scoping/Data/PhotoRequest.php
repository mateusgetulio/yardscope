<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;

final readonly class PhotoRequest
{
    public function __construct(
        public string $message,
        public ?Section $section,
        public ?ServiceType $service,
    ) {}
}
