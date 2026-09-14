<?php

namespace App\Evals;

use App\Scoping\Data\JobScope;
use App\Scoping\Data\Observation;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;

/**
 * Compares what the pipeline produced for a set with its labels. Services are matched by type
 * and section; a line with no section in the labels is a requested-but-unseen placeholder.
 */
final readonly class Scorer
{
    public function score(EvalSet $set, ?Observation $observation, ?JobScope $scope, ?string $failure): SetScore
    {
        $expectedLines = array_values(array_filter($set->expected['lines'] ?? [], 'is_array'));
        $expectedServices = array_map(fn (array $line): string => self::key($line['type'] ?? '', $line['section'] ?? null), $expectedLines);
        $observedLines = $scope === null ? [] : array_values(array_filter($scope->lines, fn (ScopeLine $line): bool => $line->disposition !== LineDisposition::Rejected));
        $observedServices = array_map(fn (ScopeLine $line): string => self::key($line->type->value, $line->section?->value), $observedLines);
        $hallucinated = array_values(array_diff($observedServices, $expectedServices));

        $counts = [];
        $dispositions = [];
        $countingPhotos = [];

        foreach ($expectedLines as $expected) {
            $key = self::key($expected['type'] ?? '', $expected['section'] ?? null);
            $match = $this->find($observedLines, $key);

            if (isset($expected['quantity']) && is_int($expected['quantity'])) {
                $observedQuantity = $match?->current->quantity;
                $counts[] = [
                    'service' => $key,
                    'expected' => $expected['quantity'],
                    'observed' => $observedQuantity,
                    'exact' => $observedQuantity === $expected['quantity'],
                    'withinOne' => $observedQuantity !== null && abs($observedQuantity - $expected['quantity']) <= 1,
                ];
            }

            if (is_string($expected['disposition'] ?? null)) {
                $dispositions[] = [
                    'service' => $key,
                    'expected' => $expected['disposition'],
                    'observed' => $match?->disposition->value,
                    'correct' => $match?->disposition->value === $expected['disposition'],
                ];
            }

            if (isset($expected['counting_photo']) && is_int($expected['counting_photo'])) {
                $countingPhotos[] = [
                    'service' => $key,
                    'expected' => $expected['counting_photo'],
                    'observed' => $match?->countingEvidence?->photo,
                    'correct' => $match?->countingEvidence?->photo === $expected['counting_photo'],
                ];
            }
        }

        return new SetScore(
            $set->slug,
            $set->scenario,
            $observation !== null,
            $failure,
            $expectedServices,
            $observedServices,
            $hallucinated,
            $counts,
            $dispositions,
            $countingPhotos,
            is_string($set->expected['readiness'] ?? null) ? $set->expected['readiness'] : null,
            $scope?->readiness->value,
            $this->photoRequestCorrect($set, $observedLines),
            $this->unusablePhotosCorrect($set, $observation),
        );
    }

    /**
     * @param  list<ScopeLine>  $lines
     */
    private function find(array $lines, string $key): ?ScopeLine
    {
        foreach ($lines as $line) {
            if (self::key($line->type->value, $line->section?->value) === $key) {
                return $line;
            }
        }

        return null;
    }

    /**
     * The expected photo request names a service and, optionally, a section; correct when a
     * needs_photos line for that service carries a request for that section.
     *
     * @param  list<ScopeLine>  $lines
     */
    private function photoRequestCorrect(EvalSet $set, array $lines): ?bool
    {
        $expected = $set->expected['photo_request'] ?? null;

        if (! is_array($expected) || ! is_string($expected['service'] ?? null)) {
            return null;
        }

        foreach ($lines as $line) {
            if ($line->disposition !== LineDisposition::NeedsPhotos || $line->type->value !== $expected['service'] || $line->photoRequest === null) {
                continue;
            }

            if (! isset($expected['section']) || $line->photoRequest->section?->value === $expected['section']) {
                return true;
            }
        }

        return false;
    }

    private function unusablePhotosCorrect(EvalSet $set, ?Observation $observation): ?bool
    {
        $expected = $set->expected['unusable_photos'] ?? null;

        if (! is_array($expected)) {
            return null;
        }

        if ($observation === null) {
            return false;
        }

        $unusable = [];

        foreach ($observation->photos as $photo) {
            if (! $photo->usable) {
                $unusable[] = $photo->photo;
            }
        }

        sort($unusable);
        $expected = array_values($expected);
        sort($expected);

        return $unusable === $expected;
    }

    private static function key(mixed $type, mixed $section): string
    {
        return (is_string($type) ? $type : '?').'@'.(is_string($section) ? $section : 'none');
    }
}
