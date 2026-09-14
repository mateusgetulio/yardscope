<?php

use App\Scoping\CorrectionApplier;
use App\Scoping\Data\AccessNote;
use App\Scoping\Data\BriefLine;
use App\Scoping\Data\Correction;
use App\Scoping\Data\Evidence;
use App\Scoping\Data\LineEstimate;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoDescription;
use App\Scoping\Data\ReadinessRollup;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\CorrectionField;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\PhotoView;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use App\Scoping\Enums\ValueOrigin;
use App\Scoping\Exceptions\InvalidCorrection;
use App\Scoping\ProBriefBuilder;
use App\Scoping\ScopeBuilder;

it('INV-1 never prices a line without evidence, and never a count without one counting view', function () {
    foreach (pricedRandomScopes() as $seed => [, , $scope]) {
        $violations = [];

        foreach ($scope->priceableLines() as $line) {
            if ($line->type->isCounted() && ($line->countingEvidence === null || $line->current->quantity === null)) {
                $violations[] = "{$line->id} counted without a counting view";
            }

            if (! $line->type->isCounted() && $line->evidence === []) {
                $violations[] = "{$line->id} cleanup without evidence";
            }
        }

        expect($violations)->toBe([], invariantFailure('INV-1', $seed));
    }
});

it('INV-2 prices only validated lines, never rejected ones', function () {
    foreach (pricedRandomScopes() as $seed => [$observation, , $scope, $estimate]) {
        $priced = array_map(fn (LineEstimate $line): string => $line->lineId, $estimate?->lines ?? []);
        $allowed = array_map(fn (ScopeLine $line): string => $line->id, $scope->priceableLines());
        $placeholders = count(array_filter($scope->lines, fn (ScopeLine $line): bool => $line->isPlaceholder()));

        expect(array_values(array_diff($priced, $allowed)))->toBe([], invariantFailure('INV-2', $seed))
            ->and(count($scope->rejected))->toBe(count($observation->rejected), invariantFailure('INV-2', $seed))
            ->and(count($scope->lines))->toBe(count($observation->lines) + $placeholders, invariantFailure('INV-2', $seed));
    }
});

it('INV-3 gives gated, suggested and rejected lines no price and no hours', function () {
    foreach (pricedRandomScopes() as $seed => [, , $scope, $estimate]) {
        $priced = array_map(fn (LineEstimate $line): string => $line->lineId, $estimate?->lines ?? []);
        $gated = array_map(fn (ScopeLine $line): string => $line->id, array_filter($scope->lines, fn (ScopeLine $line): bool => ! $line->isPriceable()));

        expect(array_values(array_intersect($priced, $gated)))->toBe([], invariantFailure('INV-3', $seed));

        if ($scope->priceableLines() === []) {
            expect($estimate)->toBeNull(invariantFailure('INV-3', $seed));
        }
    }
});

it('INV-4 is deterministic', function () {
    $builder = new ScopeBuilder;
    $pricer = pricer();

    foreach (pricedRandomScopes() as $seed => [$observation, $profile, $scope, $estimate]) {
        $again = $builder->build($observation, $profile);

        expect(serialize($again))->toBe(serialize($scope), invariantFailure('INV-4', $seed))
            ->and(serialize($pricer->estimate($again)))->toBe(serialize($estimate), invariantFailure('INV-4', $seed));
    }
});

it('INV-5 never lowers the price or the hours when more work is added within the priceable range', function () {
    $applier = new CorrectionApplier;
    $pricer = pricer();

    foreach (pricedRandomScopes() as $seed => [, , $scope, $estimate]) {
        $raises = [];

        foreach ($scope->priceableLines() as $line) {
            if ($line->type->isCounted() && $line->current->quantity < Observation::MAX_QUANTITY) {
                $raises[] = new Correction($line->id, CorrectionField::Quantity, '', (string) ($line->current->quantity + 1), null);
            }

            if (in_array($line->type, [ServiceType::ShrubTrimming, ServiceType::BranchRemoval], true) && $line->current->size === Size::Small) {
                $raises[] = new Correction($line->id, CorrectionField::Size, '', 'medium', null);
            }

            if (in_array($line->type, [ServiceType::YardCleanup, ServiceType::BedWeeding], true) && $line->current->severity !== Severity::Heavy) {
                $raises[] = new Correction($line->id, CorrectionField::Severity, '', $line->current->severity === Severity::Light ? 'moderate' : 'heavy', null);
            }
        }

        foreach ($raises as $raise) {
            $raised = $pricer->estimate($applier->apply($scope, $raise));

            expect($raised?->priceCents)->toBeGreaterThanOrEqual($estimate?->priceCents ?? 0, invariantFailure('INV-5', $seed))
                ->and($raised?->hours->low)->toBeGreaterThanOrEqual($estimate?->hours->low ?? 0.0, invariantFailure('INV-5', $seed))
                ->and($raised?->hours->high)->toBeGreaterThanOrEqual($estimate?->hours->high ?? 0.0, invariantFailure('INV-5', $seed));
        }

        $extra = new ScopeLine('extra', ServiceType::BranchRemoval, Section::FrontYard, new LineValues(1, Size::Small, null), new LineValues(1, Size::Small, null), LineDisposition::Priceable, [], null, null, new Evidence(1, 'x'), [], [], null, true);
        $withExtra = $pricer->estimate($scope->withLines([...$scope->lines, $extra]));

        expect($withExtra?->priceCents)->toBeGreaterThanOrEqual($estimate?->priceCents ?? 0, invariantFailure('INV-5', $seed))
            ->and($withExtra?->hours->high)->toBeGreaterThan($estimate?->hours->high ?? 0.0, invariantFailure('INV-5', $seed));
    }
});

it('INV-6 keeps corrections within bounds and re-gates the ones that cross a rule', function () {
    $applier = new CorrectionApplier;
    $pricer = pricer();

    foreach (pricedRandomScopes() as $seed => [, , $scope, $estimate]) {
        foreach ($scope->priceableLines() as $line) {
            if ($line->type->isCounted()) {
                expect(fn () => $applier->apply($scope, new Correction($line->id, CorrectionField::Quantity, '', '0', null)))->toThrow(InvalidCorrection::class);
                expect(fn () => $applier->apply($scope, new Correction($line->id, CorrectionField::Quantity, '', (string) (Observation::MAX_QUANTITY + 1), null)))->toThrow(InvalidCorrection::class);
            }

            if (in_array($line->type, [ServiceType::ShrubTrimming, ServiceType::BranchRemoval], true)) {
                $large = $applier->apply($scope, new Correction($line->id, CorrectionField::Size, '', 'large', null));

                expect($large->line($line->id)?->disposition)->toBe(LineDisposition::ManualQuote, invariantFailure('INV-6', $seed))
                    ->and($pricer->estimate($large)?->priceCents ?? 0)->toBeLessThanOrEqual($estimate?->priceCents ?? 0, invariantFailure('INV-6', $seed));
            }

            $removed = $applier->apply($scope, new Correction($line->id, CorrectionField::Removed, '', '', null));

            expect($pricer->estimate($removed)?->priceCents ?? 0)->toBeLessThanOrEqual($estimate?->priceCents ?? 0, invariantFailure('INV-6', $seed));
        }
    }
});

it('INV-8 lets access notes and suggested lines change nothing about the price', function () {
    $builder = new ScopeBuilder;
    $pricer = pricer();

    foreach (pricedRandomScopes() as $seed => [$observation, $profile, $scope, $estimate]) {
        $withoutSuggestions = $scope->withLines(array_values(array_filter($scope->lines, fn (ScopeLine $line): bool => $line->disposition !== LineDisposition::Suggested)));
        $noAccess = $builder->build(new Observation($observation->photos, $observation->requestedInSentence, $observation->lines, $observation->rejected, new AccessNote(false, []), $observation->hazards, $observation->modelNotes), $profile);

        expect($pricer->estimate($withoutSuggestions)?->priceCents)->toBe($estimate?->priceCents, invariantFailure('INV-8', $seed))
            ->and($pricer->estimate($noAccess)?->priceCents)->toBe($estimate?->priceCents, invariantFailure('INV-8', $seed));
    }
});

it('INV-9 derives readiness from dispositions, and a better photo never makes a line worse', function () {
    $builder = new ScopeBuilder;

    foreach (pricedRandomScopes() as $seed => [$observation, $profile, $scope]) {
        expect(ReadinessRollup::from($scope->lines))->toBe($scope->readiness, invariantFailure('INV-9', $seed));

        $photos = $observation->photos;
        $photos[] = new PhotoDescription(count($photos) + 1, PhotoView::Wide, Section::cases(), true);
        $improved = $builder->build(new Observation($photos, $observation->requestedInSentence, $observation->lines, $observation->rejected, $observation->access, $observation->hazards, $observation->modelNotes, $observation->unsupportedRequests), $profile);
        $violations = [];

        foreach ($scope->lines as $index => $before) {
            $after = $improved->lines[$index];
            $worse = ($before->disposition === LineDisposition::Priceable && $after->disposition !== LineDisposition::Priceable)
                || ($before->disposition === LineDisposition::ManualQuote && $after->disposition !== LineDisposition::ManualQuote)
                || ($before->disposition === LineDisposition::NeedsPhotos && ! in_array($after->disposition, [LineDisposition::NeedsPhotos, LineDisposition::Priceable], true));

            if ($worse) {
                $violations[] = "{$before->id} went from {$before->disposition->value} to {$after->disposition->value}";
            }
        }

        expect($violations)->toBe([], invariantFailure('INV-9', $seed));
    }
});

it('INV-7 builds the brief from the scope alone, with an origin on every value', function () {
    $builder = new ProBriefBuilder;
    $applier = new CorrectionApplier;

    foreach (pricedRandomScopes() as $seed => [, , $scope, $estimate]) {
        // Correct the first counted priceable line so both origins appear.
        foreach ($scope->priceableLines() as $line) {
            if ($line->type->isCounted() && $line->current->quantity !== null && $line->current->quantity < Observation::MAX_QUANTITY) {
                $scope = $applier->apply($scope, new Correction($line->id, CorrectionField::Quantity, '', (string) ($line->current->quantity + 1), 'seen one more'));
                break;
            }
        }

        $brief = $builder->build($scope, $estimate);
        $fromScope = array_map(fn (ScopeLine $line): array => [$line->id, $line->type, $line->section, $line->current->quantity, $line->current->size, $line->current->severity, $line->origin()], $scope->lines);
        $fromBrief = array_map(fn (BriefLine $line): array => [$line->lineId, $line->type, $line->section, $line->values->quantity, $line->values->size, $line->values->severity, $line->origin], $brief->lines);

        expect($fromBrief)->toBe($fromScope, invariantFailure('INV-7', $seed));

        foreach ($brief->lines as $line) {
            $source = $scope->line($line->lineId);

            expect($line->origin === ValueOrigin::CustomerCorrected)->toBe($line->observed !== null, invariantFailure('INV-7', $seed))
                ->and($line->customerReason)->toBe($source?->lastCorrection()?->reason, invariantFailure('INV-7', $seed));
        }

        $photoNumbers = array_map(fn ($photo): int => $photo->photo, $scope->photos);
        $scopeNotes = [];

        foreach ($scope->lines as $line) {
            foreach (array_filter([$line->countingEvidence, ...$line->supportingEvidence, ...$line->evidence]) as $evidence) {
                $scopeNotes[] = $evidence->note;
            }
        }

        foreach ([...$scope->access->evidence, ...array_merge([], ...array_map(fn ($hazard): array => $hazard->evidence, $scope->hazards))] as $evidence) {
            $scopeNotes[] = $evidence->note;
        }

        expect(array_keys($brief->photoNotes))->toBe($photoNumbers, invariantFailure('INV-7', $seed));

        foreach ($brief->photoNotes as $notes) {
            foreach ($notes as $note) {
                expect(array_any($scopeNotes, fn (string $known): bool => str_ends_with($note, ": {$known}")))->toBeTrue(invariantFailure('INV-7', $seed));
            }
        }
    }
});
