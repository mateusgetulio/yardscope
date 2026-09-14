<?php

namespace App\Http\Presenters;

use App\Models\JobRequest;
use App\Requests\AssembledScope;
use App\Scoping\Data\Estimate;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;
use App\Scoping\Enums\ServiceType;

final readonly class ScopePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(JobRequest $request, AssembledScope $assembled): array
    {
        $scope = $assembled->scope;
        $estimate = $assembled->estimate;
        $excluded = array_values(array_filter($scope->lines, fn (ScopeLine $line): bool => in_array($line->disposition, [LineDisposition::ManualQuote, LineDisposition::NeedsPhotos], true)));

        return [
            'id' => $request->id,
            'sentence' => $request->sentence,
            'booked' => $request->isBooked(),
            'photos' => array_map(fn (array $photo): array => ['number' => $photo['number'], 'url' => route('requests.photo', [$request, $photo['number']])], $request->photos),
            'canAddPhoto' => count($request->photos) < 4 && ! $request->isBooked(),
            'profile' => $request->profile,
            'readiness' => $scope->readiness->value,
            'readinessLabel' => $scope->readiness->label(),
            'requestNote' => $scope->requestNote,
            'estimate' => $estimate === null ? null : $this->estimate($estimate),
            'cta' => $this->callToAction($scope->readiness, $estimate),
            'excludedSummary' => $excluded === [] ? null : $this->excludedSummary($excluded),
            'lines' => array_map(fn (ScopeLine $line): array => $this->line($line, $estimate), $scope->lines),
            'rejected' => array_map(fn ($line): array => ['type' => $line->type, 'reason' => $line->reason], $scope->rejected),
            'access' => ['narrowGatePossible' => $scope->access->narrowGatePossible],
            'hazards' => array_map(fn ($hazard): array => ['section' => $hazard->section->label(), 'note' => $hazard->note], $scope->hazards),
            'unsupportedRequests' => $assembled->unsupportedRequests,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function estimate(Estimate $estimate): array
    {
        return [
            'priceCents' => $estimate->priceCents,
            'price' => $this->money($estimate->priceCents),
            'hours' => $this->hours($estimate->shownLowHours, $estimate->shownHighHours),
            'visitFee' => $this->money($estimate->visitFeeCents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function callToAction(RequestReadiness $readiness, ?Estimate $estimate): array
    {
        $price = $estimate === null ? null : $this->money($estimate->priceCents);

        return match ($readiness) {
            RequestReadiness::Ready => ['label' => "Book this job, {$price}", 'enabled' => true],
            RequestReadiness::Partial => ['label' => "Book priced work, {$price}", 'enabled' => true],
            RequestReadiness::NeedsPhotos => ['label' => 'Add the requested photo to get a price', 'enabled' => false],
            RequestReadiness::ManualQuote => ['label' => 'A pro will quote this on site', 'enabled' => false],
        };
    }

    /**
     * @param  list<ScopeLine>  $excluded
     */
    private function excludedSummary(array $excluded): string
    {
        $names = array_map(fn (ScopeLine $line): string => strtolower($line->type->label()), $excluded);
        $list = count($names) === 1 ? $names[0] : implode(', ', array_slice($names, 0, -1)).' and '.end($names);
        $verb = count($names) === 1 ? 'is' : 'are';

        return ucfirst("{$list} {$verb} not included. A pro will quote {$this->pronoun(count($names))} separately.");
    }

    private function pronoun(int $count): string
    {
        return $count === 1 ? 'it' : 'them';
    }

    /**
     * @return array<string, mixed>
     */
    private function line(ScopeLine $line, ?Estimate $estimate): array
    {
        $lineEstimate = null;

        foreach ($estimate === null ? [] : $estimate->lines as $candidate) {
            if ($candidate->lineId === $line->id) {
                $lineEstimate = $candidate;
            }
        }

        return [
            'id' => $line->id,
            'type' => $line->type->value,
            'label' => $line->type->label(),
            'section' => $line->section?->label(),
            'disposition' => $line->disposition->value,
            'dispositionLabel' => $line->disposition->label(),
            'summary' => $this->summary($line),
            'quantity' => $line->current->quantity,
            'size' => $line->current->size?->value,
            'severity' => $line->current->severity?->value,
            'counted' => $line->type->isCounted(),
            'usesSize' => in_array($line->type, [ServiceType::ShrubTrimming, ServiceType::BranchRemoval], true),
            'origin' => $line->origin()->value,
            'observedSummary' => $line->origin()->value === 'customer_corrected' ? $this->summaryOf($line->observed, $line) : null,
            'note' => $line->note,
            'photoRequest' => $line->photoRequest?->message,
            'checksPassed' => $line->passedChecks(),
            'checksTotal' => count($line->checks),
            'checks' => array_map(fn ($check): array => ['rule' => $check->rule->value, 'passed' => $check->passed, 'message' => $check->message], $line->checks),
            'evidence' => array_map(fn ($item): array => ['photo' => $item->photo, 'note' => $item->note], array_values(array_filter([$line->countingEvidence, ...$line->supportingEvidence, ...$line->evidence]))),
            'hours' => $lineEstimate === null ? null : $this->hours($lineEstimate->hours->low, $lineEstimate->hours->high),
            'labor' => $lineEstimate === null ? null : $this->money($lineEstimate->laborCents),
            'removed' => $line->wasRemoved(),
            'placeholder' => $line->isPlaceholder(),
        ];
    }

    private function summary(ScopeLine $line): string
    {
        return $this->summaryOf($line->current, $line);
    }

    private function summaryOf(LineValues $values, ScopeLine $line): string
    {
        if ($line->isPlaceholder()) {
            return 'Not visible in the photos yet';
        }

        $parts = [];

        if ($values->quantity !== null) {
            $parts[] = $values->quantity.' '.($values->quantity === 1 ? $this->singular($line) : strtolower($this->plural($line)));
        }

        if ($values->size !== null) {
            $parts[] = $values->size->value;
        }

        if ($values->severity !== null) {
            $parts[] = $values->severity->value.' '.($line->type->isCounted() ? 'growth' : 'cleanup');
        }

        return ucfirst(implode(', ', $parts));
    }

    private function singular(ScopeLine $line): string
    {
        return match ($line->type) {
            ServiceType::ShrubTrimming => 'shrub',
            ServiceType::BedWeeding => 'bed',
            ServiceType::BranchRemoval => 'branch',
            default => 'item',
        };
    }

    private function plural(ScopeLine $line): string
    {
        return $this->singular($line).($line->type === ServiceType::BranchRemoval ? 'es' : 's');
    }

    private function money(int $cents): string
    {
        return '$'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }

    private function hours(float $low, float $high): string
    {
        $format = fn (float $hours): string => rtrim(rtrim(number_format($hours, 1), '0'), '.');

        return $low === $high ? "{$format($low)} h" : "{$format($low)} to {$format($high)} h";
    }
}
