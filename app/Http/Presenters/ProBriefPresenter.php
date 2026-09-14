<?php

namespace App\Http\Presenters;

use App\Models\JobRequest;
use App\Models\ProAction;
use App\Scoping\Data\BriefLine;
use App\Scoping\Data\Evidence;
use App\Scoping\Data\LineValues;
use App\Scoping\Data\ProBrief;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\ValueOrigin;

final readonly class ProBriefPresenter
{
    /**
     * @return array<string, mixed>
     */
    public function present(JobRequest $request, ProBrief $brief): array
    {
        return [
            'id' => $request->id,
            'sentence' => $request->sentence,
            'readinessLabel' => $brief->readiness->label(),
            'booked' => $request->isBooked(),
            'bookedPrice' => $request->booked_price_cents === null ? null : $this->money($request->booked_price_cents),
            'profile' => $request->profile,
            'lines' => array_map(fn (BriefLine $line): array => $this->line($line), $brief->lines),
            'photos' => array_map(fn (array $photo): array => [
                'number' => $photo['number'],
                'url' => route('requests.photo', [$request, $photo['number']]),
                'notes' => $brief->photoNotes[$photo['number']] ?? [],
            ], $request->photos),
            'accessNotes' => $brief->accessNotes,
            'openQuestions' => $brief->openQuestions,
            'estimate' => $brief->estimate === null ? null : [
                'price' => $this->money($brief->estimate->priceCents),
                'hours' => $this->hours($brief->estimate->shownLowHours, $brief->estimate->shownHighHours),
            ],
            'actions' => $request->proActions->map(fn (ProAction $action): array => [
                'id' => $action->id,
                'kind' => $action->kind,
                'reason' => $action->reason,
                'adjustedPrice' => $action->adjusted_price_cents === null ? null : $this->money($action->adjusted_price_cents),
                'at' => $action->created_at->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function line(BriefLine $line): array
    {
        return [
            'id' => $line->lineId,
            'label' => $line->type->label(),
            'section' => $line->section?->label(),
            'disposition' => $line->disposition->value,
            'dispositionLabel' => $line->disposition->label(),
            'summary' => $this->summary($line->values, $line->type, $line->section === null),
            'origin' => $line->origin->value,
            'originLabel' => $line->origin === ValueOrigin::CustomerCorrected ? 'Customer-corrected' : 'AI-observed',
            'observedSummary' => $line->observed === null ? null : $this->summary($line->observed, $line->type, false),
            'customerReason' => $line->customerReason,
            'removedByCustomer' => $line->removedByCustomer,
            'evidence' => array_map(fn (Evidence $item): array => ['photo' => $item->photo, 'note' => $item->note], $line->evidence),
            'note' => $line->note,
        ];
    }

    private function summary(LineValues $values, ServiceType $type, bool $placeholder): string
    {
        if ($placeholder) {
            return 'Not visible in the photos yet';
        }

        $parts = [];

        if ($values->quantity !== null) {
            $parts[] = $values->quantity.' '.$this->unit($type, $values->quantity);
        }

        if ($values->size !== null) {
            $parts[] = $values->size->value;
        }

        if ($values->severity !== null) {
            $parts[] = $values->severity->value.' '.($type->isCounted() ? 'growth' : 'cleanup');
        }

        return ucfirst(implode(', ', $parts));
    }

    private function unit(ServiceType $type, int $count): string
    {
        $singular = match ($type) {
            ServiceType::ShrubTrimming => 'shrub',
            ServiceType::BedWeeding => 'bed',
            ServiceType::BranchRemoval => 'branch',
            default => 'item',
        };

        return $count === 1 ? $singular : $singular.($type === ServiceType::BranchRemoval ? 'es' : 's');
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
