<?php

namespace App\Scoping\Enums;

enum ServiceType: string
{
    case YardCleanup = 'yard_cleanup';
    case ShrubTrimming = 'shrub_trimming';
    case BedWeeding = 'bed_weeding';
    case BranchRemoval = 'branch_removal';

    public function isCounted(): bool
    {
        return $this !== self::YardCleanup;
    }

    public function label(): string
    {
        return match ($this) {
            self::YardCleanup => 'Yard cleanup',
            self::ShrubTrimming => 'Shrub trimming',
            self::BedWeeding => 'Bed weeding',
            self::BranchRemoval => 'Branch removal',
        };
    }
}
