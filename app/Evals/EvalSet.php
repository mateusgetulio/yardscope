<?php

namespace App\Evals;

use App\Scoping\Data\PhotoInput;
use App\Scoping\Data\PropertyProfile;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use InvalidArgumentException;

/**
 * One labeled photo set: the sentence, the photos in order, and what the pipeline must produce.
 */
final readonly class EvalSet
{
    /**
     * @param  list<PhotoInput>  $photos
     * @param  array<string, mixed>  $expected
     */
    public function __construct(
        public string $slug,
        public string $scenario,
        public string $sentence,
        public array $photos,
        public PropertyProfile $profile,
        public array $expected,
    ) {}

    public static function load(string $directory): self
    {
        $file = $directory.'/labels.json';

        if (! is_file($file)) {
            throw new InvalidArgumentException("No labels.json in {$directory}.");
        }

        $labels = json_decode((string) file_get_contents($file), true);

        if (! is_array($labels) || ! is_string($labels['sentence'] ?? null) || ! is_array($labels['photos'] ?? null) || ! is_array($labels['expected'] ?? null)) {
            throw new InvalidArgumentException("labels.json in {$directory} needs sentence, photos and expected.");
        }

        $photos = [];

        foreach (array_values($labels['photos']) as $index => $name) {
            $path = $directory.'/'.$name;

            if (! is_string($name) || ! is_file($path)) {
                throw new InvalidArgumentException("Photo [{$name}] listed in {$file} is missing.");
            }

            $photos[] = new PhotoInput($index + 1, $path, mime_content_type($path) ?: null);
        }

        self::validateExpected($labels['expected'], count($photos), $file);

        return new self(
            basename($directory),
            is_string($labels['scenario'] ?? null) ? $labels['scenario'] : basename($directory),
            $labels['sentence'],
            $photos,
            PropertyProfile::fromArray(is_array($labels['profile'] ?? null) ? $labels['profile'] : ['backyard' => 'medium', 'side_yard' => 'small', 'front_yard' => 'small']),
            $labels['expected'],
        );
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private static function validateExpected(array $expected, int $photoCount, string $file): void
    {
        $fail = fn (string $problem) => throw new InvalidArgumentException("{$file}: {$problem}");
        $oneOf = fn (mixed $value, array $cases, string $name) => in_array($value, array_map(fn ($case) => $case->value, $cases), true) ? null : $fail("{$name} [".json_encode($value).'] is not one of '.implode(', ', array_map(fn ($case) => $case->value, $cases)).'.');

        if (isset($expected['readiness'])) {
            $oneOf($expected['readiness'], RequestReadiness::cases(), 'readiness');
        }

        foreach ($expected['unusable_photos'] ?? [] as $number) {
            if (! is_int($number) || $number < 1 || $number > $photoCount) {
                $fail('unusable_photos must list photo numbers of this set.');
            }
        }

        if (isset($expected['photo_request'])) {
            $oneOf($expected['photo_request']['service'] ?? null, ServiceType::cases(), 'photo_request.service');

            if (isset($expected['photo_request']['section'])) {
                $oneOf($expected['photo_request']['section'], Section::cases(), 'photo_request.section');
            }
        }

        foreach ($expected['lines'] ?? [] as $index => $line) {
            if (! is_array($line)) {
                $fail("line {$index} must be an object.");
            }

            $known = ['type', 'section', 'quantity', 'size', 'severity', 'counting_photo', 'disposition', 'optional'];

            foreach (array_keys($line) as $field) {
                if (! in_array($field, $known, true)) {
                    $fail("line {$index} has an unknown field [{$field}].");
                }
            }

            $oneOf($line['type'] ?? null, ServiceType::cases(), "line {$index} type");

            if (($line['section'] ?? null) !== null) {
                $oneOf($line['section'], Section::cases(), "line {$index} section");
            }

            if (isset($line['disposition'])) {
                $oneOf($line['disposition'], LineDisposition::cases(), "line {$index} disposition");
            }

            if (isset($line['size'])) {
                $oneOf($line['size'], Size::cases(), "line {$index} size");
            }

            if (isset($line['severity'])) {
                $oneOf($line['severity'], Severity::cases(), "line {$index} severity");
            }

            if (isset($line['quantity']) && (! is_int($line['quantity']) || $line['quantity'] < 1)) {
                $fail("line {$index} quantity must be a positive integer.");
            }

            if (isset($line['counting_photo']) && (! is_int($line['counting_photo']) || $line['counting_photo'] < 1 || $line['counting_photo'] > $photoCount)) {
                $fail("line {$index} counting_photo must be a photo number of this set.");
            }
        }
    }
}
