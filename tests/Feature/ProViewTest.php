<?php

use App\Extraction\YardObservationAgent;
use App\Models\JobRequest;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\ProviderConnectionException;

beforeEach(function () {
    Storage::fake('local');
    $this->fixtures = scratchDirectory();
    config()->set('yardscope.extraction.fixtures', $this->fixtures);
});

it('shows the pro a brief with the origin of every value and the customer reason', function () {
    $request = submittedWorkedExample($this->fixtures);
    correct($request, 'line-2', 'quantity', '6', '4', 'Two more behind the shed');
    correct($request, 'line-3', 'removed', '', '', 'Neighbor already took it');

    $this->get(route('requests.pro', $request))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('pro')
            ->where('brief.readinessLabel', 'Ready to book')
            ->where('brief.lines.0.originLabel', 'AI-observed')
            ->where('brief.lines.0.observedSummary', null)
            ->where('brief.lines.1.summary', '6 shrubs, medium')
            ->where('brief.lines.1.originLabel', 'Customer-corrected')
            ->where('brief.lines.1.observedSummary', '4 shrubs, medium')
            ->where('brief.lines.1.customerReason', 'Two more behind the shed')
            ->where('brief.lines.2.removedByCustomer', true)
            ->where('brief.lines.2.customerReason', 'Neighbor already took it')
            ->where('brief.photos.0.notes', ['Yard cleanup: leaves and debris along fence line and patio', 'Shrub trimming: wide view shows four distinct shrubs along back fence'])
            ->where('brief.photos.2.notes', ['Access: side gate looks narrower than a mower deck'])
            ->has('brief.accessNotes', 1)
            ->where('brief.openQuestions', ['From the analysis: No photo shows the left side of the backyard.'])
            ->where('brief.estimate.price', fn (string $price): bool => str_starts_with($price, '$'))
            ->has('pipeline.dispositions', 3)
            ->where('pipeline.corrections.0.field', 'quantity')
            ->where('pipeline.corrections.0.source', 'customer')
            ->where('pipeline.pricing.priceCents', fn (int $cents): bool => $cents > 16500));
});

it('records pro actions and shows the customer the latest one', function () {
    $request = submittedWorkedExample($this->fixtures);

    $this->post(route('requests.pro.actions', $request), ['kind' => 'request_photo', 'reason' => 'One photo of the gate with a tape measure'])
        ->assertRedirect(route('requests.pro', $request));
    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.proMessage', 'Your pro asked for a photo: One photo of the gate with a tape measure'));

    $this->post(route('requests.pro.actions', $request), ['kind' => 'adjust_quote', 'reason' => 'Gate is 30 inches, hand tools only', 'adjusted_price' => '185'])
        ->assertRedirect();
    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('request.proMessage', 'Your pro adjusted the quote to $185: Gate is 30 inches, hand tools only'));

    $this->post(route('requests.pro.actions', $request), ['kind' => 'accept_scope'])->assertRedirect();
    $this->get(route('requests.pro', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->has('brief.actions', 3)
        ->where('brief.actions.1.adjustedPrice', '$185')
        ->where('brief.actions.2.kind', 'accept_scope'));
});

it('validates pro actions', function () {
    $request = submittedWorkedExample($this->fixtures);

    $this->post(route('requests.pro.actions', $request), ['kind' => 'shout'])->assertSessionHasErrors('kind');
    $this->post(route('requests.pro.actions', $request), ['kind' => 'request_photo'])->assertSessionHasErrors(['reason' => 'Tell the customer why.']);
    $this->post(route('requests.pro.actions', $request), ['kind' => 'adjust_quote', 'reason' => 'x'])->assertSessionHasErrors(['adjusted_price' => 'Give the adjusted price.']);
    $this->post(route('requests.pro.actions', $request), ['kind' => 'adjust_quote', 'reason' => 'x', 'adjusted_price' => '-5'])->assertSessionHasErrors('adjusted_price');

    expect($request->proActions()->count())->toBe(0);
});

it('puts the pipeline panel values on the result page, including carried and skipped corrections', function () {
    $request = submittedWorkedExample($this->fixtures);
    correct($request, 'line-2', 'quantity', '6', '4');

    $this->get(route('requests.show', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('pipeline.extraction.driver', 'fixtures')
        ->has('pipeline.extraction.runs', 1)
        ->where('pipeline.extraction.runs.0.lines', 3)
        ->where('pipeline.photos.0.view', 'wide')
        ->where('pipeline.photos.0.usable', true)
        ->where('pipeline.validation.rejected', [])
        ->where('pipeline.dispositions.2.disposition', 'manual_quote')
        ->where('pipeline.dispositions.2.checks.0.rule', 'R5')
        ->where('pipeline.pricing.visitFeeCents', 2900)
        ->where('pipeline.corrections.0.modelValue', '4')
        ->where('pipeline.corrections.0.customerValue', '6')
        ->where('pipeline.corrections.0.skipped', false)
        ->where('pipeline.corrections.0.current', true));
});

it('shows a failed run in the pipeline panel', function () {
    config()->set('yardscope.extraction.driver', 'api');
    Ai::fakeAgent(YardObservationAgent::class, [fn () => throw new ProviderConnectionException('timed out')]);
    $this->post('/requests', ['sentence' => 'Clean up the whole backyard please', 'photos' => demoUploads(2)]);
    $request = JobRequest::sole();

    $this->get(route('requests.pro', $request))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('pipeline.extraction.runs.0.failure', fn (string $failure): bool => str_contains($failure, 'timed out'))
        ->where('pipeline.photos.0.view', null)
        ->where('pipeline.pricing', null)
        ->where('brief.lines', [])
        ->where('brief.openQuestions', ['From the analysis: We could not read these photos automatically. A pro will quote this job on site.']));
});
