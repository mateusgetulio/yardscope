<?php

namespace App\Http\Presenters;

use App\Models\JobRequest;
use App\Models\ProAction;
use App\Scoping\Data\BriefLine;
use App\Scoping\Data\Evidence;
use App\Scoping\Data\ProBrief;
use App\Scoping\Enums\ValueOrigin;

final readonly class ProBriefPresenter
{
    public function __construct(private Formats $formats = new Formats) {}

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
            'bookedPrice' => $request->booked_price_cents === null ? null : $this->formats->money($request->booked_price_cents),
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
                'price' => $this->formats->money($brief->estimate->priceCents),
                'hours' => $this->formats->hours($brief->estimate->shownLowHours, $brief->estimate->shownHighHours),
            ],
            'actions' => $request->proActions->map(fn (ProAction $action): array => [
                'id' => $action->id,
                'kind' => $action->kind->value,
                'label' => $action->kind->label(),
                'reason' => $action->reason,
                'adjustedPrice' => $action->adjusted_price_cents === null ? null : $this->formats->money($action->adjusted_price_cents),
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
            'summary' => $this->formats->summary($line->values, $line->type, $line->section === null),
            'origin' => $line->origin->value,
            'originLabel' => $line->origin === ValueOrigin::CustomerCorrected ? 'Customer-corrected' : 'AI-observed',
            'observedSummary' => $line->observed === null ? null : $this->formats->summary($line->observed, $line->type, false),
            'customerReason' => $line->customerReason,
            'removedByCustomer' => $line->removedByCustomer,
            'evidence' => array_map(fn (Evidence $item): array => ['photo' => $item->photo, 'note' => $item->note], $line->evidence),
            'note' => $line->note,
        ];
    }
}
