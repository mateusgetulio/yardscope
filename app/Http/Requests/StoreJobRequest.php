<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sentence' => ['required', 'string', 'min:10', 'max:300'],
            'photos' => ['required', 'array', 'min:2', 'max:4'],
            'photos.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sentence.min' => 'Tell us a little more: at least 10 characters.',
            'photos.min' => 'Add at least two photos.',
            'photos.max' => 'Up to four photos.',
            'photos.*.mimes' => 'Photos must be JPEG, PNG or WebP.',
            'photos.*.max' => 'Each photo must be under 10 MB.',
            'photos.*.dimensions' => 'Each photo must be at most 8000 pixels wide and tall.',
        ];
    }
}
