<?php

namespace App\Http\Requests;

use App\Scoping\Enums\CorrectionField;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCorrectionRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'line_id' => ['required', 'string', 'max:20'],
            'field' => ['required', Rule::enum(CorrectionField::class)],
            'model_value' => ['nullable', 'string', 'max:20'],
            'customer_value' => ['nullable', 'string', 'max:20'],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }
}
