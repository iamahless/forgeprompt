<?php

namespace App\Http\Requests;

use App\Models\Generation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGenerationRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if (is_array($this->input('reference_images'))) {
            // Rows the user left blank are not reference images.
            $this->merge(['reference_images' => array_values(array_filter($this->input('reference_images')))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:'.Generation::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::in(Generation::SIZES)],
            'quality' => ['required', Rule::in(Generation::QUALITIES)],
            'reference_images' => ['array', 'max:'.Generation::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference_images' => 'reference images',
            'reference_images.*' => 'reference image URL',
        ];
    }
}
