<?php

namespace App\Console\Commands;

use App\Extraction\AgentExtractor;
use App\Extraction\FixtureExtractor;
use App\Providers\AppServiceProvider;
use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoInput;
use App\Scoping\Exceptions\ExtractionFailed;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('yardscope:record {sentence : What the customer asked for} {photos* : Photo paths, in order}')]
#[Description('Run the vision model once and save its observation as a fixture for fixture mode and the evals')]
class RecordObservation extends Command
{
    public function handle(AgentExtractor $extractor): int
    {
        $sentence = $this->argument('sentence');
        $paths = $this->argument('photos');
        $photos = [];

        if (count($paths) < 2 || count($paths) > 4) {
            $this->error('Record 2 to 4 photos, the same range the request form accepts.');

            return self::FAILURE;
        }

        foreach ($paths as $index => $path) {
            if (! is_file($path)) {
                $this->error("Photo not found: {$path}");

                return self::FAILURE;
            }

            $photos[] = new PhotoInput($index + 1, $path);
        }

        $directory = AppServiceProvider::fixturesDirectory();

        try {
            $answer = $extractor->observe($photos, $sentence);
        } catch (ExtractionFailed $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $target = (new FixtureExtractor($directory))->pathFor($photos, $sentence);
        file_put_contents($target, json_encode([
            'sentence' => $sentence,
            'photos' => array_map(fn (PhotoInput $photo): string => basename($photo->path), $photos),
            'recorded_at' => now()->toIso8601String(),
            'observation' => $answer,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $observation = Observation::fromArray($answer);
        $this->info("Recorded {$target}");
        $this->line(count($observation->lines).' service lines, '.count($observation->rejected).' rejected, '.count($observation->usablePhotos()).' usable photos.');

        return self::SUCCESS;
    }
}
