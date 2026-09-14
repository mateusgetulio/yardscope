<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;

final readonly class JobScope
{
    /**
     * @param  list<PhotoDescription>  $photos
     * @param  list<ScopeLine>  $lines
     * @param  list<RejectedLine>  $rejected
     * @param  list<Hazard>  $hazards
     */
    public function __construct(
        public PropertyProfile $profile,
        public array $photos,
        public array $lines,
        public array $rejected,
        public AccessNote $access,
        public array $hazards,
        public RequestReadiness $readiness,
        public ?string $requestNote,
    ) {}

    public static function unreadable(PropertyProfile $profile, string $reason): self
    {
        return new self($profile, [], [], [], new AccessNote(false, []), [], RequestReadiness::ManualQuote, $reason);
    }

    /**
     * @return list<ScopeLine>
     */
    public function priceableLines(): array
    {
        return $this->linesWith(LineDisposition::Priceable);
    }

    /**
     * @return list<ScopeLine>
     */
    public function linesWith(LineDisposition $disposition): array
    {
        return array_values(array_filter($this->lines, fn (ScopeLine $line): bool => $line->disposition === $disposition));
    }

    public function line(string $id): ?ScopeLine
    {
        foreach ($this->lines as $line) {
            if ($line->id === $id) {
                return $line;
            }
        }

        return null;
    }

    /**
     * @param  list<ScopeLine>  $lines
     */
    public function withLines(array $lines): self
    {
        return new self(
            $this->profile,
            $this->photos,
            $lines,
            $this->rejected,
            $this->access,
            $this->hazards,
            ReadinessRollup::from($lines),
            $this->requestNote,
        );
    }
}
