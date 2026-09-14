<?php

use Inertia\Testing\AssertableInertia;

it('renders the request page', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('request'));
});
