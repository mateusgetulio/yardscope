<?php

use App\Extraction\ClaudeCodeExtractor;
use App\Extraction\RecordsObservations;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\VisionExtractor;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

beforeEach(function () {
    $this->photos = scratchPhotos(3, scratchDirectory());
    config()->set('yardscope.extraction.driver', 'claude-code');
});

/**
 * What the CLI prints in print mode with --output-format json.
 */
function claudeReply(mixed $structured, string $session = 'session-1', bool $error = false): string
{
    return json_encode(['type' => 'result', 'subtype' => $error ? 'error' : 'success', 'is_error' => $error, 'session_id' => $session, 'result' => $error ? 'Not logged in' : 'ok', 'structured_output' => $structured], JSON_THROW_ON_ERROR);
}

it('is the extractor when the driver is claude-code', function () {
    expect(app(VisionExtractor::class))->toBeInstanceOf(ClaudeCodeExtractor::class)
        ->and(app(RecordsObservations::class))->toBeInstanceOf(ClaudeCodeExtractor::class);
});

it('runs the CLI in print mode with the agent schema, the photo directory and every photo path', function () {
    Process::fake(['*' => Process::result(claudeReply(workedExample()['observation']))]);

    $observation = app(VisionExtractor::class)->extract($this->photos, 'My backyard is a mess.');

    expect($observation->lines)->toHaveCount(3);
    Process::assertRan(function (PendingProcess $process): bool {
        $command = is_array($process->command) ? $process->command : [];
        $schema = $command[array_search('--json-schema', $command, true) + 1] ?? '';
        $prompt = (string) $process->input;

        return $command[0] === 'claude'
            && in_array('--print', $command, true)
            && in_array('--output-format', $command, true)
            && str_contains($schema, '"service_lines"')
            && str_contains($schema, '"counting_evidence"')
            && in_array('--allowedTools', $command, true)
            && in_array('Read', $command, true)
            && in_array('--add-dir', $command, true)
            && in_array(dirname($this->photos[0]->path), $command, true)
            && $process->path === dirname($this->photos[0]->path)
            && ($process->environment['USER'] ?? '') !== ''
            && ($process->environment['HOME'] ?? '') !== ''
            && ! in_array('--resume', $command, true)
            && str_contains($prompt, 'Customer request: My backyard is a mess.')
            && str_contains($prompt, "Photo 3: {$this->photos[2]->path}");
    });
    Process::assertRanTimes(fn (): bool => true, 1);
});

it('asks once more in the same session with the parser message when the answer cannot be read', function () {
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(claudeReply(['photos' => 'nope'], 'session-7')))
        ->push(Process::result(claudeReply(workedExample()['observation'], 'session-7')))]);

    $observation = app(VisionExtractor::class)->extract($this->photos, 'Clean it up');

    expect($observation->lines)->toHaveCount(3);
    Process::assertRanTimes(fn (): bool => true, 2);
    Process::assertRan(function (PendingProcess $process): bool {
        $command = is_array($process->command) ? $process->command : [];

        return in_array('--resume', $command, true)
            && in_array('session-7', $command, true)
            && str_contains((string) $process->input, 'Your previous answer could not be read: photos must be a list.');
    });
});

it('gives up after the second unreadable answer instead of guessing', function () {
    Process::fake(['*' => Process::sequence()
        ->push(Process::result(claudeReply(['photos' => 'nope'])))
        ->push(Process::result(claudeReply(['photos' => 'still nope'])))]);

    expect(fn () => app(VisionExtractor::class)->extract($this->photos, 'Clean it up'))
        ->toThrow(ExtractionFailed::class, 'could not be read twice');
});

it('reports the CLI failing, erroring or answering in prose as an extraction failure', function (string $case, string $message) {
    Process::fake(['*' => match ($case) {
        'exit code' => Process::result(output: '', errorOutput: 'command not found: claude', exitCode: 127),
        'is_error' => Process::result(claudeReply(null, error: true), exitCode: 1),
        'no structured output' => Process::result(json_encode(['is_error' => false, 'result' => 'I looked at the photos.'])),
        default => Process::result('not json at all'),
    }]);

    expect(fn () => app(VisionExtractor::class)->extract($this->photos, 'Clean it up'))
        ->toThrow(ExtractionFailed::class, $message);
})->with([
    'exit code' => ['exit code', 'command not found: claude'],
    'is_error' => ['is_error', 'Not logged in'],
    'no structured output' => ['no structured output', 'text instead of the observation schema'],
    'not json' => ['not json', 'something other than its JSON result'],
]);

it('refuses to record in fixture mode', function () {
    config()->set('yardscope.extraction.driver', 'fixtures');

    expect(fn () => app(RecordsObservations::class))->toThrow(RuntimeException::class, 'set YARDSCOPE_EXTRACTOR to api or claude-code');
});
