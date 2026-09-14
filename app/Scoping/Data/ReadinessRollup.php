<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;

final readonly class ReadinessRollup
{
    /**
     * @param  list<ScopeLine>  $lines
     */
    public static function from(array $lines): RequestReadiness
    {
        $counted = array_filter($lines, fn (ScopeLine $line): bool => ! in_array($line->disposition, [LineDisposition::Suggested, LineDisposition::Rejected], true));
        $has = fn (LineDisposition $disposition): bool => array_any($counted, fn (ScopeLine $line): bool => $line->disposition === $disposition);

        return match (true) {
            $has(LineDisposition::Priceable) && ! $has(LineDisposition::ManualQuote) && ! $has(LineDisposition::NeedsPhotos) => RequestReadiness::Ready,
            $has(LineDisposition::Priceable) => RequestReadiness::Partial,
            $has(LineDisposition::NeedsPhotos) => RequestReadiness::NeedsPhotos,
            default => RequestReadiness::ManualQuote,
        };
    }
}
