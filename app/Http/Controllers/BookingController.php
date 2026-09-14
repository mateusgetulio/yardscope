<?php

namespace App\Http\Controllers;

use App\Http\Presenters\ScopePresenter;
use App\Models\JobRequest;
use App\Requests\ScopeAssembler;
use App\Scoping\Enums\RequestReadiness;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function store(JobRequest $jobRequest, ScopeAssembler $assembler): RedirectResponse
    {
        if ($jobRequest->isBooked()) {
            return redirect()->route('requests.booked', $jobRequest);
        }

        $assembled = $assembler->assemble($jobRequest);

        if (! in_array($assembled->scope->readiness, [RequestReadiness::Ready, RequestReadiness::Partial], true) || $assembled->estimate === null) {
            return back()->withErrors(['booking' => 'Nothing is priced yet, so there is nothing to book.']);
        }

        // The price is frozen at booking time; the scope keeps being rebuilt for the pro's view.
        $jobRequest->update([
            'booked_price_cents' => $assembled->estimate->priceCents,
            'booked_at' => now()->toImmutable(),
        ]);

        return redirect()->route('requests.booked', $jobRequest);
    }

    public function show(JobRequest $jobRequest, ScopeAssembler $assembler, ScopePresenter $presenter): Response|RedirectResponse
    {
        if (! $jobRequest->isBooked()) {
            return redirect()->route('requests.show', $jobRequest);
        }

        return Inertia::render('booked', ['request' => $presenter->present($jobRequest, $assembler->assemble($jobRequest))]);
    }
}
