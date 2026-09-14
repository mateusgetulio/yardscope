<?php

namespace App\Http\Controllers;

use App\Http\Presenters\PipelinePresenter;
use App\Http\Presenters\ProBriefPresenter;
use App\Http\Requests\StoreProActionRequest;
use App\Intake\ScopeAssembler;
use App\Models\JobRequest;
use App\Scoping\ProBriefBuilder;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProController extends Controller
{
    public function show(JobRequest $jobRequest, ScopeAssembler $assembler, ProBriefBuilder $builder, ProBriefPresenter $presenter, PipelinePresenter $pipeline): Response
    {
        $assembled = $assembler->assemble($jobRequest);

        return Inertia::render('pro', [
            'brief' => $presenter->present($jobRequest, $builder->build($assembled->scope, $assembled->estimate)),
            'pipeline' => $pipeline->present($jobRequest, $assembled),
        ]);
    }

    public function store(StoreProActionRequest $request, JobRequest $jobRequest): RedirectResponse
    {
        $jobRequest->proActions()->create([
            'kind' => $request->string('kind')->toString(),
            'reason' => $request->filled('reason') ? $request->string('reason')->toString() : null,
            'adjusted_price_cents' => $request->string('kind')->toString() === 'adjust_quote' ? (int) round($request->float('adjusted_price') * 100) : null,
        ]);

        return redirect()->route('requests.pro', $jobRequest);
    }
}
