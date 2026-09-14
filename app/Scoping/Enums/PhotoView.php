<?php

namespace App\Scoping\Enums;

enum PhotoView: string
{
    case Wide = 'wide';
    case Medium = 'medium';
    case Close = 'close';

    public function coversSection(): bool
    {
        return $this !== self::Close;
    }
}
