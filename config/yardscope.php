<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Extraction
    |--------------------------------------------------------------------------
    |
    | Fixture mode replays recorded model observations keyed by the photo
    | bytes and the sentence, so the demo and the tests run without a key.
    | Live mode sends the photos to the configured vision model.
    |
    */

    'extraction' => [
        'live' => env('YARDSCOPE_LIVE', false),
        'provider' => env('YARDSCOPE_AI_PROVIDER', 'anthropic'),
        'model' => env('YARDSCOPE_AI_MODEL', 'claude-sonnet-5'),
        'fixtures' => 'fixtures/observations',
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Card
    |--------------------------------------------------------------------------
    |
    | Synthetic demo rates, not real prices. Hours are a low and high estimate
    | per unit of work: per section for cleanups (scaled by the section size
    | from the property profile) and per item for counted services. Only lines
    | the readiness gate marked priceable are ever priced.
    |
    */

    'rates' => [
        'visit_fee_cents' => 2900,
        'hourly_rate_cents' => 4800,
        'price_rounding_cents' => 500,
        'section_scale' => ['small' => 1.0, 'medium' => 1.5, 'large' => 2.2],
        'hours' => [
            'yard_cleanup' => ['light' => [0.4, 0.6], 'moderate' => [0.7, 1.0], 'heavy' => [1.0, 1.35]],
            'shrub_trimming' => ['small' => [0.1, 0.15], 'medium' => [0.2, 0.3]],
            'bed_weeding' => ['light' => [0.3, 0.4], 'moderate' => [0.5, 0.7], 'heavy' => [0.8, 1.1]],
            'branch_removal' => ['small' => [0.25, 0.25], 'medium' => [0.5, 0.75]],
        ],
    ],

];
