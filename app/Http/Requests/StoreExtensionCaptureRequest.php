<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExtensionCaptureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'page_title' => ['required', 'string', 'max:500'],
            'source_url' => ['required', 'url', 'max:5000', 'starts_with:http://,https://'],
            'terms' => ['required', 'array', 'min:1', 'max:50'],
            'terms.*.term' => ['required', 'string', 'max:200'],
            'terms.*.explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
