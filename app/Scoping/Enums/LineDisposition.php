<?php

namespace App\Scoping\Enums;

enum LineDisposition: string
{
    case Priceable = 'priceable';
    case NeedsPhotos = 'needs_photos';
    case ManualQuote = 'manual_quote';
    case Suggested = 'suggested';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Priceable => 'Priced now',
            self::NeedsPhotos => 'Needs a photo',
            self::ManualQuote => 'Pro quote required',
            self::Suggested => 'Suggested',
            self::Rejected => 'Not included',
        };
    }
}
