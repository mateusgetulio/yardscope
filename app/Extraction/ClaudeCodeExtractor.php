<?php

namespace App\Extraction;

use App\Scoping\Data\Observation;
use App\Scoping\Data\PhotoInput;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\Exceptions\InvalidObservation;
use App\Scoping\VisionExtractor;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Laravel\Ai\ObjectSchema;

/**
 * Runs the same agent instructions and schema through the local Claude Code CLI in print mode,
 * so a developer signed in to Claude Code can run the live flow without an API key. The CLI
 * reads the photos with its own Read tool and answers through structured output. Development
 * only: it needs the CLI on the machine and the developer's own session.
 */
final readonly class ClaudeCodeExtractor implements RecordsObservations, VisionExtractor
{
    public function __construct(
        private YardObservationAgent $agent,
        private string $binary,
        private string $model,
        private int $timeoutSeconds,
    ) {}

    public function extract(array $photos, string $sentence): Observation
    {
        return Observation::fromArray($this->observe($photos, $sentence));
    }

    public function observe(array $photos, string $sentence): array
    {
        $listing = implode("\n", array_map(fn (PhotoInput $photo): string => "Photo {$photo->number}: {$photo->path}", $photos));
        $prompt = "Customer request: {$sentence}\nThere are ".count($photos).' photos. Read every one of these files with the Read tool before answering, and refer to them by their photo number:'."\n{$listing}";
        [$answer, $session] = $this->ask($photos, $prompt, null);

        try {
            Observation::fromArray($answer);

            return $answer;
        } catch (InvalidObservation $first) {
            // One repair attempt in the same session, with the parser's own message; a second failure never becomes a guess (rule R6).
            [$answer] = $this->ask($photos, "Your previous answer could not be read: {$first->getMessage()} Return the complete observation again, following the schema exactly.", $session);

            try {
                Observation::fromArray($answer);

                return $answer;
            } catch (InvalidObservation $second) {
                throw new ExtractionFailed("The model's observation could not be read twice: {$second->getMessage()}", previous: $second);
            }
        }
    }

    /**
     * @param  list<PhotoInput>  $photos
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function ask(array $photos, string $prompt, ?string $session): array
    {
        $directories = array_values(array_unique(array_map(fn (PhotoInput $photo): string => dirname($photo->path), $photos)));
        $command = [
            $this->binary, '--print', '--output-format', 'json',
            '--json-schema', json_encode((new ObjectSchema($this->agent->schema(new JsonSchemaTypeFactory)))->toSchema(), JSON_THROW_ON_ERROR),
            '--allowedTools', 'Read',
            '--model', $this->model,
            '--system-prompt', $this->agent->instructions(),
            ...($session === null ? [] : ['--resume', $session]),
        ];

        foreach ($directories as $directory) {
            $command[] = '--add-dir';
            $command[] = $directory;
        }

        // The prompt goes on stdin: --add-dir takes a list, so a trailing argument would be read as a directory.
        try {
            $result = Process::path($directories[0])->env($this->environment())->timeout($this->timeoutSeconds)->input($prompt)->run($command);
        } catch (ProcessTimedOutException $exception) {
            throw new ExtractionFailed("Claude Code did not answer within {$this->timeoutSeconds} seconds.", previous: $exception);
        }

        $reply = json_decode($result->output(), true);

        // The CLI reports its own errors (not logged in, model refused) as a JSON result with a non-zero exit.
        if (is_array($reply) && ($reply['is_error'] ?? false) === true) {
            throw new ExtractionFailed('Claude Code reported an error: '.(is_string($reply['result'] ?? null) ? $reply['result'] : 'no details'));
        }

        if ($result->failed()) {
            throw new ExtractionFailed('Claude Code could not be run: '.trim($result->errorOutput() ?: $result->output()));
        }

        if (! is_array($reply)) {
            throw new ExtractionFailed('Claude Code answered with something other than its JSON result.');
        }

        if (! is_array($reply['structured_output'] ?? null)) {
            throw new ExtractionFailed('Claude Code answered with text instead of the observation schema.');
        }

        return [$reply['structured_output'], is_string($reply['session_id'] ?? null) ? $reply['session_id'] : ''];
    }

    /**
     * A child of a web request inherits only the .env variables, and the CLI finds the signed-in
     * account through USER and HOME, so those are passed along explicitly.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        $account = function_exists('posix_getpwuid') ? posix_getpwuid(posix_geteuid()) : false;
        $candidates = [
            'HOME' => $account['dir'] ?? getenv('HOME'),
            'USER' => $account['name'] ?? getenv('USER'),
            'PATH' => getenv('PATH'),
        ];
        $environment = [];

        foreach ($candidates as $name => $value) {
            if (is_string($value) && $value !== '') {
                $environment[$name] = $value;
            }
        }

        return $environment;
    }
}
