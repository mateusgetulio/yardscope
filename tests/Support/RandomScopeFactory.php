<?php

namespace Tests\Support;

use App\Scoping\Data\Observation;
use App\Scoping\Data\PropertyProfile;
use App\Scoping\Enums\PhotoView;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\SectionSize;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class RandomScopeFactory
{
    private Randomizer $random;

    public function __construct(int $seed)
    {
        $this->random = new Randomizer(new Mt19937($seed));
    }

    public function profile(): PropertyProfile
    {
        $sizes = [];

        foreach (Section::cases() as $section) {
            $sizes[$section->value] = $this->pick(SectionSize::cases())->value;
        }

        return PropertyProfile::fromArray($sizes);
    }

    public function observation(): Observation
    {
        return Observation::fromArray($this->observationData());
    }

    /**
     * Raw model output, including the kinds of mistakes a model makes, so parsing and the gate see
     * real rejections: unknown types, counts without a counting view, evidence pointing nowhere.
     *
     * @return array<string, mixed>
     */
    public function observationData(): array
    {
        $photoCount = $this->random->getInt(1, 4);
        $photos = [];

        foreach (range(1, $photoCount) as $number) {
            $photos[] = [
                'photo' => $number,
                'view' => $this->pick(PhotoView::cases())->value,
                'sections' => array_map(fn (Section $section): string => $section->value, $this->some(Section::cases())),
                'usable' => $this->random->getInt(1, 8) !== 1,
            ];
        }

        $lines = [];

        foreach (range(1, $this->random->getInt(0, 5)) as $ignored) {
            $lines[] = $this->line($photoCount);
        }

        // The sentence usually asks for what the photos show; sometimes for one more thing, sometimes for something unsupported.
        $requested = [];

        foreach (array_unique(array_column($lines, 'type')) as $type) {
            if ($this->random->getInt(1, 5) !== 1) {
                $requested[] = $type;
            }
        }

        if ($this->random->getInt(1, 5) === 1) {
            $requested[] = $this->pick(ServiceType::cases())->value;
        }

        if ($this->random->getInt(1, 15) === 1) {
            $requested[] = 'pool_cleaning';
        }

        $hazards = $this->random->getInt(1, 10) === 1
            ? [['section' => $this->pick(Section::cases())->value, 'note' => 'something a pro should see', 'evidence' => [['photo' => 1, 'note' => 'visible here']]]]
            : [];

        return [
            'photos' => $photos,
            'requested_in_sentence' => $requested,
            'service_lines' => $lines,
            'access' => ['narrow_gate_possible' => $this->random->getInt(0, 1) === 1, 'evidence' => []],
            'hazards' => $hazards,
            'model_notes' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(int $photoCount): array
    {
        $type = $this->random->getInt(1, 20) === 1 ? 'pool_cleaning' : $this->pick(ServiceType::cases())->value;
        $evidence = fn (): array => ['photo' => $this->random->getInt(1, 15) === 1 ? 9 : $this->random->getInt(1, $photoCount), 'note' => 'seen here'];
        $line = ['type' => $type, 'section' => $this->pick(Section::cases())->value];

        if ($this->random->getInt(1, 12) === 1) {
            $line['uncertain'] = 'hard to judge';
        }

        if ($type === ServiceType::YardCleanup->value) {
            $line['severity'] = $this->pick(Severity::cases())->value;
            $line['evidence'] = $this->random->getInt(1, 10) === 1 ? [] : [$evidence()];

            return $line;
        }

        $line['quantity'] = $this->random->getInt(1, 10) === 1 ? $this->random->getInt(-1, 25) : $this->random->getInt(1, 8);
        $line[$type === ServiceType::BedWeeding->value ? 'severity' : 'size'] = $type === ServiceType::BedWeeding->value
            ? $this->pick(Severity::cases())->value
            : $this->pick(Size::cases())->value;

        if ($this->random->getInt(1, 8) !== 1) {
            $line['counting_evidence'] = $evidence();
        }

        $line['supporting_evidence'] = $this->random->getInt(0, 1) === 1 ? [$evidence()] : [];

        return $line;
    }

    /**
     * @template T
     *
     * @param  list<T>  $cases
     * @return list<T>
     */
    private function some(array $cases): array
    {
        $count = $this->random->getInt(1, count($cases));

        return array_values(array_intersect_key($cases, array_flip($this->random->pickArrayKeys($cases, $count))));
    }

    /**
     * @template T
     *
     * @param  list<T>  $cases
     * @return T
     */
    private function pick(array $cases): mixed
    {
        return $cases[$this->random->getInt(0, count($cases) - 1)];
    }
}
