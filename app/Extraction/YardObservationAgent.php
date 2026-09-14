<?php

namespace App\Extraction;

use App\Scoping\Enums\PhotoView;
use App\Scoping\Enums\Section;
use App\Scoping\Enums\ServiceType;
use App\Scoping\Enums\Severity;
use App\Scoping\Enums\Size;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

#[Temperature(0.0)]
#[Timeout(90)]
class YardObservationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        $services = implode(', ', array_map(fn (ServiceType $type): string => $type->value, ServiceType::cases()));
        $sections = implode(', ', array_map(fn (Section $section): string => $section->value, Section::cases()));

        return <<<TEXT
        You describe what a homeowner's yard photos show so that outdoor work can be scoped. You report observations only. You never estimate areas, prices or hours.

        Photos are numbered in the order they are attached, starting at 1. For each photo say whether it is a wide, medium or close view, which yard sections it shows ({$sections}) and whether it is usable (in focus, showing a yard).

        List the services the customer's sentence asks for in requested_in_sentence, using only these types: {$services}. A general request to clean up or tidy a yard means yard_cleanup; "trim whatever needs trimming" means shrub_trimming. Report what the sentence asks for and let the scope decide what that includes.

        Report one service line per service and section you can see. Every line needs evidence: the photo number and a short note saying what you saw there. For yard_cleanup give a severity (light, moderate, heavy) and at least one piece of evidence. For shrub_trimming, bed_weeding and branch_removal give a quantity and take that count from exactly one photo, the counting_evidence; never add counts from different photos, because the same object can appear in several. Other photos go in supporting_evidence. Shrubs and branches get a size (small, medium, large); beds get a severity.

        If you cannot judge something, say so in the line's uncertain field instead of guessing. Report a possibly narrow side gate under access, and anything dangerous (wires, wasps, unstable trees) under hazards with its section. Use model_notes for anything the photos do not show that a scope would need.
        TEXT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        $evidence = fn (JsonSchema $schema) => [
            'photo' => $schema->integer()->min(1)->required(),
            'note' => $schema->string()->required(),
        ];
        $enum = fn (array $cases): array => array_map(fn ($case): string => $case->value, $cases);

        return [
            'photos' => $schema->array()->items($schema->object(fn (JsonSchema $schema) => [
                'photo' => $schema->integer()->min(1)->required(),
                'view' => $schema->string()->enum($enum(PhotoView::cases()))->required(),
                'sections' => $schema->array()->items($schema->string()->enum($enum(Section::cases())))->required(),
                'usable' => $schema->boolean()->required(),
            ]))->required(),
            'requested_in_sentence' => $schema->array()->items($schema->string()->enum($enum(ServiceType::cases())))->required(),
            'service_lines' => $schema->array()->items($schema->object(fn (JsonSchema $schema) => [
                'type' => $schema->string()->enum($enum(ServiceType::cases()))->required(),
                'section' => $schema->string()->enum($enum(Section::cases()))->required(),
                'quantity' => $schema->integer()->min(1)->nullable(),
                'size' => $schema->string()->enum($enum(Size::cases()))->nullable(),
                'severity' => $schema->string()->enum($enum(Severity::cases()))->nullable(),
                'counting_evidence' => $schema->object($evidence)->nullable(),
                'supporting_evidence' => $schema->array()->items($schema->object($evidence))->required(),
                'evidence' => $schema->array()->items($schema->object($evidence))->required(),
                'uncertain' => $schema->string()->nullable(),
            ]))->required(),
            'access' => $schema->object(fn (JsonSchema $schema) => [
                'narrow_gate_possible' => $schema->boolean()->required(),
                'evidence' => $schema->array()->items($schema->object($evidence))->required(),
            ])->required(),
            'hazards' => $schema->array()->items($schema->object(fn (JsonSchema $schema) => [
                'section' => $schema->string()->enum($enum(Section::cases()))->required(),
                'note' => $schema->string()->required(),
                'evidence' => $schema->array()->items($schema->object($evidence))->required(),
            ]))->required(),
            'model_notes' => $schema->string()->nullable(),
        ];
    }
}
