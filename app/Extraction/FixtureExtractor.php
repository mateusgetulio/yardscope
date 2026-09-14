<?php

namespace App\Extraction;

use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoInput;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\InvalidObservation;
use App\Scoping\Exceptions\NoRecordedObservation;
use App\Scoping\VisionExtractor;

final readonly class FixtureExtractor implements VisionExtractor
{
    public function __construct(private string $directory) {}

    public function extract(array $photos, string $sentence): Observation
    {
        $path = $this->pathFor($photos, $sentence);

        if (! is_file($path)) {
            throw new NoRecordedObservation('No recorded observation for these photos and this sentence. Run it live once with yardscope:record, or use one of the demo sets.');
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data) || ! is_array($data['observation'] ?? null)) {
            throw new ExtractionFailed("The recorded observation at {$path} is not readable.");
        }

        try {
            return Observation::fromArray($data['observation']);
        } catch (InvalidObservation $exception) {
            throw new ExtractionFailed("The recorded observation at {$path} is invalid: {$exception->getMessage()}", previous: $exception);
        }
    }

    /**
     * @param  list<PhotoInput>  $photos
     */
    public function pathFor(array $photos, string $sentence): string
    {
        return $this->directory.'/'.self::keyFor($photos, $sentence).'.json';
    }

    /**
     * The key depends on the photo bytes and the sentence only, so the same request replays the same
     * recorded answer wherever the files live. Case, spacing and trailing punctuation do not count.
     *
     * @param  list<PhotoInput>  $photos
     */
    public static function keyFor(array $photos, string $sentence): string
    {
        $parts = [self::normalize($sentence)];

        foreach ($photos as $photo) {
            if (! is_file($photo->path)) {
                throw new ExtractionFailed("Photo {$photo->number} at {$photo->path} could not be read.");
            }

            $parts[] = hash_file('sha256', $photo->path);
        }

        return hash('sha256', implode("\n", $parts));
    }

    private static function normalize(string $sentence): string
    {
        $collapsed = preg_replace('/\s+/', ' ', mb_strtolower(trim($sentence))) ?? '';

        return rtrim($collapsed, ' .!?,;:');
    }
}
