<?php

namespace App\Evals;

/**
 * Aggregates over the set scores. Every rate is a plain fraction of what was measured; a metric
 * with nothing to measure is null rather than a flattering 1.0.
 */
final readonly class Metrics
{
    /**
     * @param  list<SetScore>  $scores
     * @return array<string, mixed>
     */
    public static function from(array $scores): array
    {
        $expected = 0;
        $observed = 0;
        $matched = 0;
        $matchedRequired = 0;
        $hallucinated = 0;
        $counts = ['total' => 0, 'exact' => 0, 'withinOne' => 0];
        $dispositions = ['total' => 0, 'correct' => 0];
        $countingPhotos = ['total' => 0, 'correct' => 0];
        $severities = ['total' => 0, 'correct' => 0];
        $sizes = ['total' => 0, 'correct' => 0];
        $readiness = ['total' => 0, 'correct' => 0];
        $photoRequests = ['total' => 0, 'correct' => 0];
        $unusable = ['total' => 0, 'correct' => 0];
        $valid = 0;

        foreach ($scores as $score) {
            $valid += $score->schemaValid ? 1 : 0;
            $expected += count($score->expectedServices);
            $observed += count($score->observedServices);
            // Recall counts the required lines the model found; precision also credits optional lines it volunteered.
            $matchedRequired += count(array_intersect($score->observedServices, $score->expectedServices));
            $matched += count(array_intersect($score->observedServices, [...$score->expectedServices, ...$score->optionalServices]));
            $hallucinated += count($score->hallucinated);

            foreach ($score->counts as $count) {
                $counts['total']++;
                $counts['exact'] += $count['exact'] ? 1 : 0;
                $counts['withinOne'] += $count['withinOne'] ? 1 : 0;
            }

            foreach ($score->dispositions as $disposition) {
                $dispositions['total']++;
                $dispositions['correct'] += $disposition['correct'] ? 1 : 0;
            }

            foreach ($score->countingPhotos as $photo) {
                $countingPhotos['total']++;
                $countingPhotos['correct'] += $photo['correct'] ? 1 : 0;
            }

            foreach ($score->attributes as $attribute) {
                if ($attribute['attribute'] === 'severity') {
                    $severities['total']++;
                    $severities['correct'] += $attribute['correct'] ? 1 : 0;
                } else {
                    $sizes['total']++;
                    $sizes['correct'] += $attribute['correct'] ? 1 : 0;
                }
            }

            foreach ([[&$readiness, $score->readinessCorrect()], [&$photoRequests, $score->photoRequestCorrect], [&$unusable, $score->unusablePhotosCorrect]] as [&$bucket, $result]) {
                if ($result !== null) {
                    $bucket['total']++;
                    $bucket['correct'] += $result ? 1 : 0;
                }
            }
        }

        $rate = fn (int $part, int $whole): ?float => $whole === 0 ? null : round($part / $whole, 3);

        return [
            'sets' => count($scores),
            'schema_valid_rate' => $rate($valid, count($scores)),
            'service_precision' => $rate($matched, $observed),
            'service_recall' => $rate($matchedRequired, $expected),
            'count_exact_rate' => $rate($counts['exact'], $counts['total']),
            'count_within_one_rate' => $rate($counts['withinOne'], $counts['total']),
            'hallucinated_lines' => $hallucinated,
            'severity_accuracy' => $rate($severities['correct'], $severities['total']),
            'size_accuracy' => $rate($sizes['correct'], $sizes['total']),
            'disposition_accuracy' => $rate($dispositions['correct'], $dispositions['total']),
            'readiness_accuracy' => $rate($readiness['correct'], $readiness['total']),
            'photo_request_accuracy' => $rate($photoRequests['correct'], $photoRequests['total']),
            'counting_photo_accuracy' => $rate($countingPhotos['correct'], $countingPhotos['total']),
            'unusable_photo_accuracy' => $rate($unusable['correct'], $unusable['total']),
        ];
    }
}
