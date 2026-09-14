<?php

namespace App\Scoping\Data;

final readonly class RejectedLine
{
    /**
     * @param  mixed  $raw  the line exactly as the model sent it, so storing an observation loses nothing
     */
    public function __construct(
        public string $type,
        public string $reason,
        public mixed $raw,
    ) {}
}
