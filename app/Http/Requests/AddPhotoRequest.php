<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AddPhotoRequest extends FormRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:max_width=8000,max_height=8000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'photo.mimes' => 'Photos must be JPEG, PNG or WebP.',
            'photo.max' => 'Each photo must be under 10 MB.',
            'photo.dimensions' => 'The photo must be at most 8000 pixels wide and tall.',
        ];
    }
}
