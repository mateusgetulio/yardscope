<?php

namespace App\Http\Presenters;

use App\Intake\AssembledScope;
use App\Models\Enums\ProActionKind;
use App\Models\JobRequest;
use App\Models\ProAction;
use App\Scoping\Data\Estimate;
use App\Scoping\Data\Evidence;
use App\Scoping\Data\Hazard;
use App\Scoping\Data\ReadinessCheck;
use App\Scoping\Data\RejectedLine;
use App\Scoping\Data\ScopeLine;
use App\Scoping\Enums\LineDisposition;
use App\Scoping\Enums\RequestReadiness;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\ValueOrigin;

final readonly class ScopePresenter
{
    public function __construct(private Formats $formats = new Formats) {}

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
            'bookedPrice' => $request->booked_price_cents === null ? null : $this->formats->money($request->booked_price_cents),
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
            'proMessage' => $this->proMessage($request),
        ];
    }

    /**
     * What the pro last asked of the customer. A photo request stays up until a newer photo
     * arrives, even if the pro accepted the scope afterwards.
     */
    private function proMessage(JobRequest $request): ?string
    {
        $latestRun = $request->latestRun();
        $photoRequest = $request->proActions
            ->filter(fn (ProAction $action): bool => $action->kind === ProActionKind::RequestPhoto && ($latestRun === null || $action->created_at >= $latestRun->created_at))
            ->last();
        $action = $photoRequest ?? $request->proActions->last();

        return match ($action?->kind) {
            ProActionKind::RequestPhoto => "Your pro asked for a photo: {$action->reason}",
            ProActionKind::AdjustQuote => 'Your pro adjusted the quote to '.$this->formats->money((int) $action->adjusted_price_cents)
                .($request->booked_price_cents === null ? '' : ' (you booked at '.$this->formats->money($request->booked_price_cents).')')
                .": {$action->reason}",
            ProActionKind::AcceptScope => 'Your pro has accepted this scope.',
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function estimate(Estimate $estimate): array
    {
        return [
            'priceCents' => $estimate->priceCents,
            'price' => $this->formats->money($estimate->priceCents),
            'hours' => $this->formats->hours($estimate->shownLowHours, $estimate->shownHighHours),
            'visitFee' => $this->formats->money($estimate->visitFeeCents),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function callToAction(RequestReadiness $readiness, ?Estimate $estimate): array
    {
        $price = $estimate === null ? null : $this->formats->money($estimate->priceCents);

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
            $sentences[] = ucfirst($this->list($photos).(count($photos) === 1 ? ' needs a photo before it can be priced.' : ' need a photo before they can be priced.'));
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
            'summary' => $this->formats->summary($line->current, $line->type, $line->isPlaceholder()),
            'quantity' => $line->current->quantity,
            'size' => $line->current->size?->value,
            'severity' => $line->current->severity?->value,
            'counted' => $line->type->isCounted(),
            'usesSize' => in_array($line->type, [ServiceType::ShrubTrimming, ServiceType::BranchRemoval], true),
            'origin' => $line->origin()->value,
            'observedSummary' => $line->origin() === ValueOrigin::CustomerCorrected ? $this->formats->summary($line->observed, $line->type, false) : null,
            'note' => $line->note,
            'photoRequest' => $line->photoRequest?->message,
            'checksPassed' => $line->passedChecks(),
            'checksTotal' => count($line->checks),
            'checks' => array_map(fn (ReadinessCheck $check): array => ['rule' => $check->rule->value, 'passed' => $check->passed, 'message' => $check->message], $line->checks),
            'evidence' => array_map(fn (Evidence $item): array => ['photo' => $item->photo, 'note' => $item->note], array_values(array_filter([$line->countingEvidence, ...$line->supportingEvidence, ...$line->evidence]))),
            'hours' => $lineEstimate === null ? null : $this->formats->hours($lineEstimate->hours->low, $lineEstimate->hours->high),
            'labor' => $lineEstimate === null ? null : $this->formats->money($lineEstimate->laborCents),
            'removed' => $line->wasRemoved(),
            'placeholder' => $line->isPlaceholder(),
            'canRemove' => $line->disposition !== LineDisposition::Rejected && $line->disposition !== LineDisposition::Suggested,
            'canAdd' => $line->disposition === LineDisposition::Suggested || $line->wasRemoved(),
            'canChange' => ! $line->isPlaceholder() && ! in_array($line->disposition, [LineDisposition::Rejected, LineDisposition::Suggested], true),
            'lastReason' => $line->lastCorrection()?->reason,
        ];
    }
}
