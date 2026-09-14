<?php

namespace App\Scoping;

use App\Scoping\Data\LineValues;
use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoRequest;
use App\Scoping\Data\ReadinessCheck;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\ReadinessRule;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Size;

final readonly class ReadinessGate
{
    public const MIN_USABLE_PHOTOS = 2;

    /**
     * Rules are applied in the spec's precedence: R5 before R1 and R2, because a line a pro must
     * quote anyway gains nothing from another photo. R4 and R6 are decided before this point.
     *
     * @return array{LineDisposition, list<ReadinessCheck>, ?PhotoRequest, ?string}
     */
    public function evaluate(ServiceType $type, Section $section, LineValues $values, ?string $uncertain, Observation $observation): array
    {
        $manualReason = $this->manualOnlyReason($type, $section, $values, $uncertain, $observation);
        $enoughPhotos = count($observation->usablePhotos()) >= self::MIN_USABLE_PHOTOS;
        $covered = $observation->covers($section);

        $checks = [
            new ReadinessCheck(ReadinessRule::ManualOnly, $manualReason === null, $manualReason ?? 'Nothing here needs a pro to look first.'),
            new ReadinessCheck(ReadinessRule::UsablePhotos, $enoughPhotos, $enoughPhotos ? 'At least two usable photos.' : 'Fewer than two usable photos.'),
            new ReadinessCheck(ReadinessRule::SectionCoverage, $covered, $covered ? "A wide or medium view shows the {$section->label()}." : "No wide or medium view shows the {$section->label()}."),
        ];

        if ($manualReason !== null) {
            return [LineDisposition::ManualQuote, $checks, null, $manualReason];
        }

        if (! $enoughPhotos) {
            return [LineDisposition::NeedsPhotos, $checks, new PhotoRequest("Take one wide photo of the {$section->label()}.", $section, null), null];
        }

        if (! $covered) {
            return [LineDisposition::NeedsPhotos, $checks, new PhotoRequest("One photo showing the whole {$section->label()}, taken from the far end.", $section, null), null];
        }

        return [LineDisposition::Priceable, $checks, null, null];
    }

    private function manualOnlyReason(ServiceType $type, Section $section, LineValues $values, ?string $uncertain, Observation $observation): ?string
    {
        if ($observation->hasHazardIn($section)) {
            return "A hazard was spotted in the {$section->label()}, so a pro has to look first.";
        }

        if ($type === ServiceType::ShrubTrimming && $values->size === Size::Large) {
            return 'Large shrubs are quoted on site.';
        }

        if ($type === ServiceType::BranchRemoval && $values->size === Size::Large) {
            return 'Large branches are quoted on site.';
        }

        if ($type === ServiceType::BranchRemoval && $uncertain !== null) {
            return "The branch could not be judged from the photos ({$uncertain}), so a pro quotes it.";
        }

        return null;
    }
}
