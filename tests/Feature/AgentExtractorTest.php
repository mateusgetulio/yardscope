<?php

use App\Extraction\AgentExtractor;
use App\Extraction\FixtureExtractor;
use App\Extraction\YardObservationAgent;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Exceptions\ExtractionFailed;
use App\Scoping\VisionExtractor;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Laravel\Ai\Ai;
use Laravel\Ai\Exceptions\ProviderConnectionException;

beforeEach(function () {
    $this->photos = scratchPhotos(3, scratchDirectory());
});

it('turns the model answer into an observation with every photo attached', function () {
    Ai::fakeAgent(YardObservationAgent::class, [workedExample()['observation'], workedExample()['observation']]);

    $observation = app(AgentExtractor::class)->extract($this->photos, 'My backyard is a mess.');

    expect($observation->lines)->toHaveCount(3)
        ->and(app(AgentExtractor::class)->observe($this->photos, 'again'))->toBe(workedExample()['observation']);

    Ai::assertAgentWasPrompted(YardObservationAgent::class, fn ($prompt): bool => str_contains($prompt->prompt, 'Customer request: My backyard is a mess.')
        && str_contains($prompt->prompt, '3 photos')
        && count($prompt->attachments) === 3);
});

it('asks once more with the parser message when the answer cannot be read', function () {
    Ai::fakeAgent(YardObservationAgent::class, [['photos' => 'nope'], workedExample()['observation']]);

    $observation = app(AgentExtractor::class)->extract($this->photos, 'Clean it up');

    expect($observation->lines)->toHaveCount(3);
    Ai::assertAgentWasPromptedTimes(YardObservationAgent::class, 2);
    Ai::assertAgentWasPrompted(YardObservationAgent::class, fn ($prompt): bool => str_contains($prompt->prompt, 'Your previous answer could not be read: photos must be a list.'));
});

it('gives up after the second unreadable answer instead of guessing', function () {
    Ai::fakeAgent(YardObservationAgent::class, [['photos' => 'nope'], ['service_lines' => 'nope', 'photos' => []]])->preventStrayPrompts();

    expect(fn () => app(AgentExtractor::class)->extract($this->photos, 'Clean it up'))
        ->toThrow(ExtractionFailed::class, 'could not be read twice');
    Ai::assertAgentWasPromptedTimes(YardObservationAgent::class, 2);
});

it('reports a provider failure as an extraction failure', function () {
    Ai::fakeAgent(YardObservationAgent::class, [fn () => throw new ProviderConnectionException('timed out')]);

    expect(fn () => app(AgentExtractor::class)->extract($this->photos, 'Clean it up'))
        ->toThrow(ExtractionFailed::class, 'The vision model could not be reached: timed out');
});

it('uses recordings by default and the model only when live mode is on', function () {
    expect(app(VisionExtractor::class))->toBeInstanceOf(FixtureExtractor::class);

    config()->set('yardscope.extraction.driver', 'api');

    expect(app(VisionExtractor::class))->toBeInstanceOf(AgentExtractor::class);
});

it('declares the observation schema the parser expects', function () {
    $compiled = array_map(fn ($type) => $type->toArray(), (new YardObservationAgent)->schema(new JsonSchemaTypeFactory));
    $line = $compiled['service_lines']['items']['properties'];

    expect(array_keys($compiled))->toBe(['photos', 'requested_in_sentence', 'service_lines', 'access', 'hazards', 'model_notes'])
        ->and($line['quantity']['type'])->toBe(['integer', 'null'])
        ->and($line['counting_evidence']['type'])->toBe(['object', 'null'])
        ->and($line['counting_evidence']['required'])->toBe(['photo', 'note'])
        ->and($compiled['service_lines']['items']['required'])->toContain('supporting_evidence', 'evidence', 'type', 'section')
        ->and($line['type']['enum'])->toBe(array_map(fn ($case) => $case->value, ServiceType::cases()))
        ->and($line['size']['enum'])->toBe(['small', 'medium', 'large'])
        ->and($compiled['photos']['items']['properties']['view']['enum'])->toBe(['wide', 'medium', 'close']);
});
