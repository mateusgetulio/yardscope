<?php

use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->results = scratchDirectory().'/results';
    mkdir($this->results);
    config()->set('yardscope.evals.results', $this->results);
});

it('says when no run has been recorded', function () {
    $this->get('/evals')->assertInertia(fn (AssertableInertia $page) => $page->component('evals')->where('results', null)->where('file', null));
});

it('shows the newest results file as written', function () {
    file_put_contents($this->results.'/2026-09-01.json', json_encode(['ran_at' => '2026-09-01T10:00:00+00:00', 'mode' => 'fixtures', 'driver' => 'fixtures', 'metrics' => ['sets' => 1], 'sets' => []]));
    file_put_contents($this->results.'/2026-09-14.json', json_encode(['ran_at' => '2026-09-14T10:00:00+00:00', 'mode' => 'live', 'driver' => 'claude-code', 'metrics' => ['sets' => 12, 'service_recall' => 0.917], 'sets' => []]));

    $this->get('/evals')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('file', '2026-09-14.json')
        ->where('results.mode', 'live')
        ->where('results.metrics.service_recall', 0.917));
});
