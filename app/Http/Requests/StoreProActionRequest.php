<?php

namespace App\Http\Requests;

use App\Models\Enums\ProActionKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProActionRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(ProActionKind::class)],
            'reason' => [Rule::requiredIf(fn (): bool => $this->input('kind') !== 'accept_scope'), 'nullable', 'string', 'max:200'],
            'adjusted_price' => [Rule::requiredIf(fn (): bool => $this->input('kind') === 'adjust_quote'), 'nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Tell the customer why.',
            'adjusted_price.required' => 'Give the adjusted price.',
        ];
    }
}
