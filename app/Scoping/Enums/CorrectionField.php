<?php

namespace App\Scoping\Enums;

enum CorrectionField: string
{
    case Quantity = 'quantity';
    case Size = 'size';
    case Severity = 'severity';
    case Removed = 'removed';
    case Added = 'added';

    public function changesValue(): bool
    {
        return in_array($this, [self::Quantity, self::Size, self::Severity], true);
    }
}
