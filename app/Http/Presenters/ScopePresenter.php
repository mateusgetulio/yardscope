<?php

namespace App\Http\Presenters;

use App\Intake\AssembledScope;
use App\Models\JobRequest;
use App\Scoping\Data\Estimate;
use App\Scoping\Data\Evidence;
use App\Scoping\Data\Hazard;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\ReadinessCheck;
use App\Scoping\Data\RejectedLine;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\ValueOrigin;

final readonly class ScopePresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(JobRequest $request, AssembledScope $assembled): array
    {
        $scope = $assembled->scope;
        $estimate = $assembled->estimate;

        return [
            'id' => $request->id,
            'sentence' => $request->sentence,
            'booked' => $request->isBooked(),
            'bookedPrice' => $request->booked_price_cents === null ? null : $this->money($request->booked_price_cents),
            'photos' => array_map(fn (array $photo): array => ['number' => $photo['number'], 'url' => route('requests.photo', [$request, $photo['number']])], $request->photos),
            'canAddPhoto' => count($request->photos) < 4 && ! $request->isBooked(),
            'profile' => $request->profile,
            'readiness' => $scope->readiness->value,
            'readinessLabel' => $scope->readiness->label(),
            'requestNote' => $scope->requestNote,
            'estimate' => $estimate === null ? null : $this->estimate($estimate),
            'cta' => $this->callToAction($scope->readiness, $estimate),
            'excludedSummary' => $this->excludedSummary($scope->lines),
            'lines' => array_map(fn (ScopeLine $line): array => $this->line($line, $estimate), $scope->lines),
            'rejected' => array_map(fn (RejectedLine $line): array => ['label' => ServiceType::tryFrom($line->type)?->label() ?? 'One service', 'reason' => $line->reason], $scope->rejected),
            'access' => ['narrowGatePossible' => $scope->access->narrowGatePossible],
            'hazards' => array_map(fn (Hazard $hazard): array => ['section' => $hazard->section->label(), 'note' => $hazard->note], $scope->hazards),
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
     * Names every gated line under the price, so the total is never read as covering it. Lines
     * that only need a photo are told apart from lines a pro has to see.
     *
     * @param  list<ScopeLine>  $lines
     */
    private function excludedSummary(array $lines): ?string
    {
        $names = fn (LineDisposition $disposition): array => array_map(
            fn (ScopeLine $line): string => strtolower($line->type->label()),
            array_values(array_filter($lines, fn (ScopeLine $line): bool => $line->disposition === $disposition)),
        );
        $sentences = [];

        if (($photos = $names(LineDisposition::NeedsPhotos)) !== []) {
            $sentences[] = ucfirst($this->list($photos).(count($photos) === 1 ? ' needs' : ' need').' a photo before it can be priced.');
        }

        if (($manual = $names(LineDisposition::ManualQuote)) !== []) {
            $sentences[] = ucfirst($this->list($manual).(count($manual) === 1 ? ' is' : ' are').' not included. A pro will quote '.(count($manual) === 1 ? 'it' : 'them').' separately.');
        }

        return $sentences === [] ? null : implode(' ', $sentences);
    }

    /**
     * @param  list<string>  $names
     */
    private function list(array $names): string
    {
        return count($names) === 1 ? $names[0] : implode(', ', array_slice($names, 0, -1)).' and '.end($names);
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
            'observedSummary' => $line->origin() === ValueOrigin::CustomerCorrected ? $this->summaryOf($line->observed, $line) : null,
            'note' => $line->note,
            'photoRequest' => $line->photoRequest?->message,
            'checksPassed' => $line->passedChecks(),
            'checksTotal' => count($line->checks),
            'checks' => array_map(fn (ReadinessCheck $check): array => ['rule' => $check->rule->value, 'passed' => $check->passed, 'message' => $check->message], $line->checks),
            'evidence' => array_map(fn (Evidence $item): array => ['photo' => $item->photo, 'note' => $item->note], array_values(array_filter([$line->countingEvidence, ...$line->supportingEvidence, ...$line->evidence]))),
            'hours' => $lineEstimate === null ? null : $this->hours($lineEstimate->hours->low, $lineEstimate->hours->high),
            'labor' => $lineEstimate === null ? null : $this->money($lineEstimate->laborCents),
            'removed' => $line->wasRemoved(),
            'placeholder' => $line->isPlaceholder(),
            'canRemove' => $line->disposition !== LineDisposition::Rejected && $line->disposition !== LineDisposition::Suggested,
            'canAdd' => $line->disposition === LineDisposition::Suggested || $line->wasRemoved(),
            'canChange' => ! $line->isPlaceholder() && ! in_array($line->disposition, [LineDisposition::Rejected, LineDisposition::Suggested], true),
            'lastReason' => $line->lastCorrection()?->reason,
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
