<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCorrectionRequest;
use App\Intake\ScopeAssembler;
use App\Models\JobRequest;
use App\Scoping\CorrectionApplier;
use App\Scoping\Data\Correction;
use App\Scoping\Enums\CorrectionField;
use App\Scoping\Exceptions\InvalidCorrection;
use Illuminate\Http\RedirectResponse;

class CorrectionController extends Controller
{
    public function store(StoreCorrectionRequest $request, JobRequest $jobRequest, ScopeAssembler $assembler, CorrectionApplier $applier): RedirectResponse
    {
        if ($jobRequest->isBooked()) {
            return back()->withErrors(['correction' => 'This job is booked. Changes now go through your pro.']);
        }

        $assembled = $assembler->assemble($jobRequest);

        if ($assembled->run === null || $assembled->run->observation === null) {
            return back()->withErrors(['correction' => 'There is no scope to correct on this request.']);
        }

        $correction = new Correction(
            $request->string('line_id')->toString(),
            $request->enum('field', CorrectionField::class) ?? CorrectionField::Quantity,
            $request->string('model_value')->toString(),
            $request->string('customer_value')->toString(),
            $request->filled('reason') ? $request->string('reason')->toString() : null,
        );

        // Apply first so an invalid correction is refused by the domain and never stored.
        try {
            $scope = $applier->apply($assembled->scope, $correction);
        } catch (InvalidCorrection $exception) {
            return back()->withErrors(['correction' => $exception->getMessage()]);
        }

        $assembled->run->corrections()->create([
            'line_id' => $correction->lineId,
            'field' => $correction->field,
            'model_value' => $correction->modelValue,
            'customer_value' => $correction->customerValue,
            'reason' => $correction->reason,
            'source' => 'customer',
        ]);
        $jobRequest->update(['readiness' => $scope->readiness->value]);

        return redirect()->route('requests.show', $jobRequest);
    }
}
