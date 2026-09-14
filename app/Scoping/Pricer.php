<?php

namespace App\Scoping;

use App\Scoping\Data\Estimate;
use App\Scoping\Data\HoursRange;
use App\Scoping\Data\JobScope;
use App\Scoping\Data\LineEstimate;
use App\Scoping\Data\RateCard;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Exceptions\UnpriceableLine;

final readonly class Pricer
{
    public function __construct(private RateCard $rates) {}

    public function estimate(JobScope $scope): ?Estimate
    {
        $priceable = $scope->priceableLines();

        if ($priceable === []) {
            return null;
        }

        $lines = [];
        $total = new HoursRange(0.0, 0.0);

        foreach ($priceable as $line) {
            $hours = $this->hoursFor($line, $scope);
            $lines[] = new LineEstimate($line->id, $hours, (int) round($hours->midpoint() * $this->rates->hourlyRateCents));
            $total = $total->plus($hours);
        }

        $laborCents = (int) round($total->midpoint() * $this->rates->hourlyRateCents);
        $rounding = $this->rates->priceRoundingCents;

        return new Estimate(
            hours: $total,
            // Shown as whole half hours so the range never promises more precision than the rates carry.
            shownLowHours: floor($total->low * 2) / 2,
            shownHighHours: ceil($total->high * 2) / 2,
            visitFeeCents: $this->rates->visitFeeCents,
            priceCents: (int) ceil(($this->rates->visitFeeCents + $laborCents) / $rounding) * $rounding,
            lines: $lines,
        );
    }

    private function hoursFor(ScopeLine $line, JobScope $scope): HoursRange
    {
        $values = $line->current;

        if ($line->type === ServiceType::YardCleanup) {
            $hours = $values->severity === null ? null : $this->rates->cleanupHours($values->severity, $scope->profile->sizeOf($line->section));

            return $hours ?? throw new UnpriceableLine("Line {$line->id} has no cleanup rate for its severity.");
        }

        $bucket = $line->type === ServiceType::BedWeeding ? $values->severity : $values->size;
        $hours = $bucket === null ? null : $this->rates->itemHours($line->type, $bucket);

        if ($hours === null || $values->quantity === null) {
            throw new UnpriceableLine("Line {$line->id} has no rate for {$line->type->value} {$bucket?->value}.");
        }

        return $hours->times($values->quantity);
    }
}
