<?php

namespace App\Evals;

use App\Scoping\Data\PhotoInput;
use App\Scoping\Data\PropertyProfile;
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

        return new self(
            basename($directory),
            is_string($labels['scenario'] ?? null) ? $labels['scenario'] : basename($directory),
            $labels['sentence'],
            $photos,
            PropertyProfile::fromArray(is_array($labels['profile'] ?? null) ? $labels['profile'] : ['backyard' => 'medium', 'side_yard' => 'small', 'front_yard' => 'small']),
            $labels['expected'],
        );
    }
}
