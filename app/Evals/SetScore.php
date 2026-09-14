<?php

namespace App\Evals;

/**
 * How one set did, in raw counts. The aggregate metrics are computed from these, never edited.
 */
final readonly class SetScore
{
    /**
     * @param  list<string>  $expectedServices  "type@section" keys the labels list
     * @param  list<string>  $observedServices  distinct "type@section" keys the model produced (placeholders excluded)
     * @param  list<string>  $hallucinated  observed services with no support in the labels
     * @param  list<array{service: string, expected: int, observed: int|null, exact: bool, withinOne: bool}>  $counts
     * @param  list<array{service: string, expected: string, observed: string|null, correct: bool}>  $dispositions
     * @param  list<array{service: string, expected: int, observed: int|null, correct: bool}>  $countingPhotos
     * @param  list<array{service: string, attribute: string, expected: string, observed: string|null, correct: bool}>  $attributes
     * @param  list<string>  $optionalServices  present in the photos but not requested; neither expected nor hallucinated
     * @param  array<string, mixed>|null  $observation  the model's answer as parsed, kept so the run can be audited later
     */
    public function __construct(
        public string $slug,
        public string $scenario,
        public bool $schemaValid,
        public ?string $failure,
        public array $expectedServices,
        public array $observedServices,
        public int $duplicateLines,
        public array $hallucinated,
        public array $counts,
        public array $dispositions,
        public array $countingPhotos,
        public array $attributes,
        public array $optionalServices,
        public ?string $expectedReadiness,
        public ?string $observedReadiness,
        public ?bool $photoRequestCorrect,
        public ?bool $unusablePhotosCorrect,
        public ?array $observation,
    ) {}

    public function readinessCorrect(): ?bool
    {
        return $this->expectedReadiness === null ? null : $this->expectedReadiness === $this->observedReadiness;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug' => $this->slug,
            'scenario' => $this->scenario,
            'schema_valid' => $this->schemaValid,
            'failure' => $this->failure,
            'expected_services' => $this->expectedServices,
            'observed_services' => $this->observedServices,
            'duplicate_lines' => $this->duplicateLines,
            'hallucinated' => $this->hallucinated,
            'counts' => $this->counts,
            'dispositions' => $this->dispositions,
            'counting_photos' => $this->countingPhotos,
            'attributes' => $this->attributes,
            'optional_services' => $this->optionalServices,
            'expected_readiness' => $this->expectedReadiness,
            'observed_readiness' => $this->observedReadiness,
            'readiness_correct' => $this->readinessCorrect(),
            'photo_request_correct' => $this->photoRequestCorrect,
            'unusable_photos_correct' => $this->unusablePhotosCorrect,
            'observation' => $this->observation,
        ];
    }
}
