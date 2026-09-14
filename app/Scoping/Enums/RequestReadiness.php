<?php

namespace App\Scoping\Enums;

enum RequestReadiness: string
{
    case Ready = 'ready';
    case Partial = 'partial';
    case NeedsPhotos = 'needs_photos';
    case ManualQuote = 'manual_quote';

    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready to book',
            self::Partial => 'Priced work bookable now',
            self::NeedsPhotos => 'Needs photos',
            self::ManualQuote => 'Pro quote required',
        };
    }
}
