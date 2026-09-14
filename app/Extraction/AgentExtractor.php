<?php

namespace App\Extraction;

use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoInput;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\InvalidObservation;
use App\Scoping\VisionExtractor;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

final readonly class AgentExtractor implements VisionExtractor
{
    public function __construct(
        private YardObservationAgent $agent,
        private string $provider,
        private string $model,
    ) {}

    public function extract(array $photos, string $sentence): Observation
    {
        return Observation::fromArray($this->observe($photos, $sentence));
    }

    /**
     * The model's observation as an array, after at most one repair attempt. The record command
     * saves exactly this.
     *
     * @param  list<PhotoInput>  $photos
     * @return array<string, mixed>
     *
     * @throws ExtractionFailed
     */
    public function observe(array $photos, string $sentence): array
    {
        $attachments = array_map(fn (PhotoInput $photo): Image => Image::fromPath($photo->path, $photo->mimeType), $photos);
        $prompt = "Customer request: {$sentence}\nThere are ".count($photos).' photos, numbered 1 to '.count($photos).' in the order attached.';
        $answer = $this->ask($prompt, $attachments);

        try {
            Observation::fromArray($answer);

            return $answer;
        } catch (InvalidObservation $first) {
            // One repair attempt with the parser's own message; a second failure never becomes a guess (rule R6).
            $answer = $this->ask("{$prompt}\n\nYour previous answer could not be read: {$first->getMessage()} Return the complete observation again, following the schema exactly.", $attachments);

            try {
                Observation::fromArray($answer);

                return $answer;
            } catch (InvalidObservation $second) {
                throw new ExtractionFailed("The model's observation could not be read twice: {$second->getMessage()}", previous: $second);
            }
        }
    }

    /**
     * @param  list<Image>  $attachments
     * @return array<string, mixed>
     */
    private function ask(string $prompt, array $attachments): array
    {
        try {
            $response = $this->agent->prompt($prompt, attachments: $attachments, provider: $this->provider, model: $this->model);
        } catch (AiException|RuntimeException $exception) {
            throw new ExtractionFailed("The vision model could not be reached: {$exception->getMessage()}", previous: $exception);
        }

        if (! $response instanceof StructuredAgentResponse) {
            throw new ExtractionFailed('The vision model answered with text instead of the observation schema.');
        }

        return $response->toArray();
    }
}
