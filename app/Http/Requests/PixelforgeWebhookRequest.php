<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shape of a Pixelforge event. Its signature is checked earlier, by VerifyPixelforgeSignature.
 */
class PixelforgeWebhookRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'id' => ['required', 'string'],
            'type' => ['required', 'string'],
            'data' => ['array'],
        ];
    }
}
