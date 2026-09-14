<?php

namespace App\Models\Enums;

enum ProActionKind: string
{
    case AcceptScope = 'accept_scope';
    case RequestPhoto = 'request_photo';
    case AdjustQuote = 'adjust_quote';

    public function label(): string
    {
        return match ($this) {
            self::AcceptScope => 'Accepted the scope',
            self::RequestPhoto => 'Asked for a photo',
            self::AdjustQuote => 'Adjusted the quote',
        };
    }
}
