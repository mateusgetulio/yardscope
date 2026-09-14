<?php

namespace App\Scoping\Data;

use App\Scoping\Enums\PhotoView;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use App\Scoping\Exceptions\InvalidObservation;

final readonly class Observation
{
    public const MAX_QUANTITY = 20;

    /**
     * @param  list<PhotoDescription>  $photos
     * @param  list<ServiceType>  $requestedInSentence
     * @param  list<ObservedLine>  $lines
     * @param  list<RejectedLine>  $rejected
     * @param  list<Hazard>  $hazards
     * @param  list<string>  $unsupportedRequests
     */
    public function __construct(
        public array $photos,
        public array $requestedInSentence,
        public array $lines,
        public array $rejected,
        public AccessNote $access,
        public array $hazards,
        public ?string $modelNotes,
        public array $unsupportedRequests = [],
    ) {
        $numbers = array_map(fn (PhotoDescription $photo): int => $photo->photo, $photos);

        if (count($numbers) !== count(array_unique($numbers))) {
            throw new InvalidObservation('Each photo number can appear only once.');
        }
    }

    /**
     * Structural problems throw, so the caller can retry or fall back (rule R6). Problems inside a
     * single line never throw: the line is kept as rejected, so the rest of the scope still counts.
     *
     * @param  array<array-key, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $photos = array_map(self::photoFrom(...), self::listFrom($data, 'photos'));
        $photoNumbers = array_map(fn (PhotoDescription $photo): int => $photo->photo, $photos);
        $lines = [];
        $rejected = [];

        foreach (self::listFrom($data, 'service_lines') as $line) {
            $parsed = self::lineFrom($line, $photoNumbers);

            if ($parsed instanceof ObservedLine) {
                $lines[] = $parsed;
            } else {
                $rejected[] = $parsed;
            }
        }

        [$requested, $unsupported] = self::servicesFrom($data, 'requested_in_sentence');

        return new self(
            photos: $photos,
            requestedInSentence: $requested,
            lines: $lines,
            rejected: $rejected,
            access: self::accessFrom($data['access'] ?? [], $photoNumbers),
            hazards: array_map(fn (mixed $hazard): Hazard => self::hazardFrom($hazard, $photoNumbers), self::listFrom($data, 'hazards', required: false)),
            modelNotes: is_string($data['model_notes'] ?? null) ? $data['model_notes'] : null,
            unsupportedRequests: $unsupported,
        );
    }

    /**
     * @return list<PhotoDescription>
     */
    public function usablePhotos(): array
    {
        return array_values(array_filter($this->photos, fn (PhotoDescription $photo): bool => $photo->usable));
    }

    public function covers(Section $section): bool
    {
        foreach ($this->photos as $photo) {
            if ($photo->covers($section)) {
                return true;
            }
        }

        return false;
    }

    public function hasHazardIn(Section $section): bool
    {
        foreach ($this->hazards as $hazard) {
            if ($hazard->section === $section) {
                return true;
            }
        }

        return false;
    }

    /**
     * The observation in the model's own shape, so it can be stored and parsed again unchanged.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $evidence = fn (array $items): array => array_map(fn (Evidence $item): array => ['photo' => $item->photo, 'note' => $item->note], $items);

        return [
            'photos' => array_map(fn (PhotoDescription $photo): array => [
                'photo' => $photo->photo,
                'view' => $photo->view->value,
                'sections' => array_map(fn (Section $section): string => $section->value, $photo->sections),
                'usable' => $photo->usable,
            ], $this->photos),
            'requested_in_sentence' => [...array_map(fn (ServiceType $type): string => $type->value, $this->requestedInSentence), ...$this->unsupportedRequests],
            'service_lines' => [
                ...array_map(fn (ObservedLine $line): array => [
                    'type' => $line->type->value,
                    'section' => $line->section->value,
                    'quantity' => $line->values->quantity,
                    'size' => $line->values->size?->value,
                    'severity' => $line->values->severity?->value,
                    'counting_evidence' => $line->countingEvidence === null ? null : ['photo' => $line->countingEvidence->photo, 'note' => $line->countingEvidence->note],
                    'supporting_evidence' => $evidence($line->supportingEvidence),
                    'evidence' => $evidence($line->evidence),
                    'uncertain' => $line->uncertain,
                ], $this->lines),
                ...array_map(fn (RejectedLine $line): array => ['type' => $line->type, 'rejected' => $line->reason], $this->rejected),
            ],
            'access' => ['narrow_gate_possible' => $this->access->narrowGatePossible, 'evidence' => $evidence($this->access->evidence)],
            'hazards' => array_map(fn (Hazard $hazard): array => ['section' => $hazard->section->value, 'note' => $hazard->note, 'evidence' => $evidence($hazard->evidence)], $this->hazards),
            'model_notes' => $this->modelNotes,
        ];
    }

    public function wasRejected(ServiceType $type): bool
    {
        return array_any($this->rejected, fn (RejectedLine $line): bool => $line->type === $type->value);
    }

    /**
     * @return list<ServiceType>
     */
    public function observedTypes(): array
    {
        return array_values(array_unique(array_map(fn (ObservedLine $line): ServiceType => $line->type, $this->lines), SORT_REGULAR));
    }

    private static function photoFrom(mixed $photo): PhotoDescription
    {
        $photo = self::arrayFrom($photo, 'photo');
        $view = self::stringFrom($photo, 'view', 'photo');

        return new PhotoDescription(
            photo: self::intFrom($photo, 'photo', 'photo'),
            view: PhotoView::tryFrom($view) ?? throw new InvalidObservation("A photo view must be wide, medium or close, got [{$view}]."),
            sections: array_map(self::sectionFrom(...), self::listFrom($photo, 'sections')),
            usable: self::boolFrom($photo, 'usable', 'photo'),
        );
    }

    /**
     * @param  list<int>  $photoNumbers
     */
    private static function lineFrom(mixed $line, array $photoNumbers): ObservedLine|RejectedLine
    {
        if (! is_array($line)) {
            return new RejectedLine('unknown', 'A service line must be an object.');
        }

        $typeValue = is_string($line['type'] ?? null) ? $line['type'] : 'unknown';
        $type = ServiceType::tryFrom($typeValue);

        if ($type === null) {
            return new RejectedLine($typeValue, "Unknown service type [{$typeValue}].");
        }

        $reject = fn (string $reason): RejectedLine => new RejectedLine($type->value, $reason);
        $section = is_string($line['section'] ?? null) ? Section::tryFrom($line['section']) : null;

        if ($section === null) {
            return $reject('The section must be front_yard, backyard or side_yard.');
        }

        try {
            $counting = isset($line['counting_evidence']) ? self::evidenceFrom($line['counting_evidence'], $photoNumbers) : null;
            $supporting = array_map(fn (mixed $item): Evidence => self::evidenceFrom($item, $photoNumbers), self::listFrom($line, 'supporting_evidence', required: false));
            $evidence = array_map(fn (mixed $item): Evidence => self::evidenceFrom($item, $photoNumbers), self::listFrom($line, 'evidence', required: false));
        } catch (InvalidObservation $exception) {
            return $reject($exception->getMessage());
        }

        $quantity = $line['quantity'] ?? null;
        $size = is_string($line['size'] ?? null) ? Size::tryFrom($line['size']) : null;
        $severity = is_string($line['severity'] ?? null) ? Severity::tryFrom($line['severity']) : null;

        if ($type->isCounted()) {
            if (! is_int($quantity) || $quantity < 1 || $quantity > self::MAX_QUANTITY) {
                return $reject('A counted line needs a quantity between 1 and '.self::MAX_QUANTITY.'.');
            }

            if ($counting === null) {
                return $reject('A counted line needs exactly one counting view.');
            }

            if ($type !== ServiceType::BedWeeding && $size === null) {
                return $reject('A counted line needs a size of small, medium or large.');
            }

            if ($type === ServiceType::BedWeeding && $severity === null) {
                return $reject('A bed weeding line needs a severity of light, moderate or heavy.');
            }
        } else {
            if ($severity === null) {
                return $reject('A cleanup line needs a severity of light, moderate or heavy.');
            }

            if ($evidence === []) {
                return $reject('A cleanup line needs at least one piece of evidence.');
            }

            $quantity = null;
            $size = null;
            $counting = null;
        }

        if ($type->isCounted()) {
            $supporting = [...$supporting, ...$evidence];
            $evidence = [];
        }

        return new ObservedLine(
            type: $type,
            section: $section,
            values: new LineValues($quantity, $size, $severity),
            countingEvidence: $counting,
            supportingEvidence: $supporting,
            evidence: $evidence,
            uncertain: is_string($line['uncertain'] ?? null) && $line['uncertain'] !== '' ? $line['uncertain'] : null,
        );
    }

    /**
     * @param  list<int>  $photoNumbers
     */
    private static function evidenceFrom(mixed $evidence, array $photoNumbers): Evidence
    {
        $evidence = self::arrayFrom($evidence, 'evidence');
        $photo = self::intFrom($evidence, 'photo', 'evidence');

        if (! in_array($photo, $photoNumbers, true)) {
            throw new InvalidObservation("Evidence points at photo {$photo}, which is not in the request.");
        }

        return new Evidence($photo, self::stringFrom($evidence, 'note', 'evidence'));
    }

    /**
     * @param  list<int>  $photoNumbers
     */
    private static function accessFrom(mixed $access, array $photoNumbers): AccessNote
    {
        $access = self::arrayFrom($access, 'access');
        $narrow = $access['narrow_gate_possible'] ?? false;

        return new AccessNote(
            narrowGatePossible: $narrow === true,
            evidence: array_map(fn (mixed $item): Evidence => self::evidenceFrom($item, $photoNumbers), self::listFrom($access, 'evidence', required: false)),
        );
    }

    /**
     * @param  list<int>  $photoNumbers
     */
    private static function hazardFrom(mixed $hazard, array $photoNumbers): Hazard
    {
        $hazard = self::arrayFrom($hazard, 'hazard');

        return new Hazard(
            section: self::sectionFrom(self::stringFrom($hazard, 'section', 'hazard')),
            note: self::stringFrom($hazard, 'note', 'hazard'),
            evidence: array_map(fn (mixed $item): Evidence => self::evidenceFrom($item, $photoNumbers), self::listFrom($hazard, 'evidence', required: false)),
        );
    }

    private static function sectionFrom(mixed $value): Section
    {
        $section = is_string($value) ? Section::tryFrom($value) : null;

        return $section ?? throw new InvalidObservation('A section must be front_yard, backyard or side_yard.');
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array{list<ServiceType>, list<string>}
     */
    private static function servicesFrom(array $data, string $key): array
    {
        $services = [];
        $unsupported = [];

        foreach (self::listFrom($data, $key) as $value) {
            $type = is_string($value) ? ServiceType::tryFrom($value) : null;

            if ($type !== null && ! in_array($type, $services, true)) {
                $services[] = $type;
            } elseif ($type === null && is_string($value) && $value !== '' && ! in_array($value, $unsupported, true)) {
                $unsupported[] = $value;
            }
        }

        return [$services, $unsupported];
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayFrom(mixed $value, string $context): array
    {
        if (! is_array($value)) {
            throw new InvalidObservation("A {$context} must be an object.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return list<mixed>
     */
    private static function listFrom(array $data, string $key, bool $required = true): array
    {
        $value = $data[$key] ?? null;

        if ($value === null && ! $required) {
            return [];
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw new InvalidObservation("{$key} must be a list.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function intFrom(array $data, string $key, string $context): int
    {
        $value = $data[$key] ?? null;

        if (! is_int($value)) {
            throw new InvalidObservation("A {$context} needs an integer {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function stringFrom(array $data, string $key, string $context): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || $value === '') {
            throw new InvalidObservation("A {$context} needs a non-empty {$key}.");
        }

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $data
     */
    private static function boolFrom(array $data, string $key, string $context): bool
    {
        $value = $data[$key] ?? null;

        if (! is_bool($value)) {
            throw new InvalidObservation("A {$context} needs a boolean {$key}.");
        }

        return $value;
    }
}
