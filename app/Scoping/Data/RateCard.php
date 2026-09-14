<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\SectionSize;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use App\Scoping\Exceptions\InvalidRateCard;

final readonly class RateCard
{
    /**
     * @param  array<string, float>  $sectionScale  keyed by section size
     * @param  array<string, array<string, HoursRange>>  $hours  service type, then severity or size
     */
    public function __construct(
        public int $visitFeeCents,
        public int $hourlyRateCents,
        public int $priceRoundingCents,
        public array $sectionScale,
        public array $hours,
    ) {
        self::ensure($visitFeeCents >= 0, 'visit_fee_cents cannot be negative.');
        self::ensure($hourlyRateCents > 0, 'hourly_rate_cents must be positive.');
        self::ensure($priceRoundingCents > 0, 'price_rounding_cents must be positive.');

        foreach (SectionSize::cases() as $size) {
            self::ensure(($sectionScale[$size->value] ?? 0.0) > 0.0, "section_scale needs a positive {$size->value} factor.");
        }

        foreach (ServiceType::cases() as $type) {
            self::ensure(($hours[$type->value] ?? []) !== [], "hours needs at least one range for {$type->value}.");

            foreach ($hours[$type->value] ?? [] as $key => $range) {
                self::ensure($range->low >= 0.0 && $range->high >= $range->low, "hours for {$type->value} {$key} must be a low and a high, low first.");
            }
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        $hours = [];

        foreach (self::arrayFrom($config, 'hours') as $type => $ranges) {
            foreach (self::arrayFrom(['ranges' => $ranges], 'ranges') as $key => $range) {
                if (! is_array($range) || count($range) !== 2 || ! is_numeric($range[0] ?? null) || ! is_numeric($range[1] ?? null)) {
                    throw new InvalidRateCard("hours for {$type} {$key} must be a list of two numbers.");
                }

                $hours[(string) $type][(string) $key] = new HoursRange((float) $range[0], (float) $range[1]);
            }
        }

        $scale = [];

        foreach (self::arrayFrom($config, 'section_scale') as $size => $factor) {
            if (! is_numeric($factor)) {
                throw new InvalidRateCard("section_scale {$size} must be a number.");
            }

            $scale[(string) $size] = (float) $factor;
        }

        return new self(
            visitFeeCents: self::intFrom($config, 'visit_fee_cents'),
            hourlyRateCents: self::intFrom($config, 'hourly_rate_cents'),
            priceRoundingCents: self::intFrom($config, 'price_rounding_cents'),
            sectionScale: $scale,
            hours: $hours,
        );
    }

    public function cleanupHours(Severity $severity, SectionSize $size): ?HoursRange
    {
        return isset($this->hours[ServiceType::YardCleanup->value][$severity->value])
            ? $this->hours[ServiceType::YardCleanup->value][$severity->value]->times($this->sectionScale[$size->value])
            : null;
    }

    public function itemHours(ServiceType $type, Size|Severity $bucket): ?HoursRange
    {
        return $this->hours[$type->value][$bucket->value] ?? null;
    }

    private static function ensure(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new InvalidRateCard($message);
        }
    }

    /**
     * @param  array<array-key, mixed>  $config
     * @return array<array-key, mixed>
     */
    private static function arrayFrom(array $config, string $key): array
    {
        $value = $config[$key] ?? null;

        if (! is_array($value)) {
            throw new InvalidRateCard("{$key} must be an array.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $config
     */
    private static function intFrom(array $config, string $key): int
    {
        $value = $config[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidRateCard("{$key} must be an integer.");
        }

        return $value;
    }
}
