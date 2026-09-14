<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class EvalsController extends Controller
{
    public function show(): Response
    {
        $configured = config()->string('yardscope.evals.results');
        $directory = str_starts_with($configured, '/') ? $configured : base_path($configured);
        $files = glob($directory.'/*.json') ?: [];
        rsort($files);
        $latest = $files === [] ? null : json_decode((string) file_get_contents($files[0]), true);

        return Inertia::render('evals', [
            'results' => is_array($latest) ? $latest : null,
            'file' => $files === [] ? null : basename($files[0]),
        ]);
    }
}
