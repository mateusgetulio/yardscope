<?php

namespace App\Scoping\Enums;

enum Section: string
{
    case FrontYard = 'front_yard';
    case Backyard = 'backyard';
    case SideYard = 'side_yard';

    public function label(): string
    {
        return match ($this) {
            self::FrontYard => 'front yard',
            self::Backyard => 'backyard',
            self::SideYard => 'side yard',
        };
    }
}
