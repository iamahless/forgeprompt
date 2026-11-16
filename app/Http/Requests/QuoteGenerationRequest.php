<?php

namespace App\Http\Requests;

use App\Models\Generation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuoteGenerationRequest extends FormRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // A form the user hasn't typed in yet can still be priced.
        $this->merge(['prompt' => (string) $this->input('prompt')]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['string', 'max:'.Generation::MAX_PROMPT_LENGTH],
            'size' => ['required', Rule::in(Generation::SIZES)],
            'quality' => ['required', Rule::in(Generation::QUALITIES)],
            'reference_image_count' => ['required', 'integer', 'min:0', 'max:'.Generation::MAX_REFERENCE_IMAGES],
        ];
    }
}
