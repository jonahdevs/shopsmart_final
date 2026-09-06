<?php

namespace App\Http\Requests\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Images added to a product's gallery.
 *
 * The MIME list is checked against the file's contents rather than its
 * extension — `mimes:` reads the actual type — because an upload form is the
 * one place in the admin where a staff member hands the server a file it will
 * later serve back to shoppers.
 */
class ProductMediaRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'images' => ['required', 'array', 'max:10'],
            'images.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.required' => __('Choose at least one image to upload.'),
            'images.*.max' => __('Each image must be 5 MB or smaller.'),
        ];
    }
}
