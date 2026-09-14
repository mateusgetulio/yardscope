<?php

namespace App\Http\Presenters;

use App\Scoping\Data\LineValues;
use App\Scoping\Enums\ServiceType;

/**
 * Customer-facing formatting shared by the presenters.
 */
final readonly class Formats
{
    public function money(int $cents): string
    {
        return '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }

    public function hours(float $low, float $high): string
    {
        $format = fn (float $hours): string => rtrim(rtrim(number_format($hours, 1), '0'), '.');

        return $low === $high ? "{$format($low)} h" : "{$format($low)} to {$format($high)} h";
    }

    /**
     * "4 shrubs, medium", "Heavy cleanup", or the placeholder wording when no photo shows the line.
     */
    public function summary(LineValues $values, ServiceType $type, bool $placeholder): string
    {
        if ($placeholder) {
            return 'Not visible in the photos yet';
        }

        $parts = [];

        if ($values->quantity !== null) {
            $parts[] = $values->quantity.' '.$this->unit($type, $values->quantity);
        }

        if ($values->size !== null) {
            $parts[] = $values->size->value;
        }

        if ($values->severity !== null) {
            $parts[] = $values->severity->value.' '.($type->isCounted() ? 'growth' : 'cleanup');
        }

        return ucfirst(implode(', ', $parts));
    }

    private function unit(ServiceType $type, int $count): string
    {
        $singular = match ($type) {
            ServiceType::ShrubTrimming => 'shrub',
            ServiceType::BedWeeding => 'bed',
            ServiceType::BranchRemoval => 'branch',
            default => 'item',
        };

        return $count === 1 ? $singular : $singular.($type === ServiceType::BranchRemoval ? 'es' : 's');
    }
}
