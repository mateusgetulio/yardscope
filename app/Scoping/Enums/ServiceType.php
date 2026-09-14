<?php

namespace App\Scoping\Enums;

enum ServiceType: string
{
    case YardCleanup = 'yard_cleanup';
    case ShrubTrimming = 'shrub_trimming';
    case BedWeeding = 'bed_weeding';
    case BranchRemoval = 'branch_removal';

    /**
     * Work a request for this service is understood to include. A general cleanup covers fallen
     * branches, so the customer is not asked to name every piece of debris.
     *
     * @return list<self>
     */
    public function implies(): array
    {
        return match ($this) {
            self::YardCleanup => [self::BranchRemoval],
            default => [],
        };
    }

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
