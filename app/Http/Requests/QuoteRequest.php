<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The options that decide a generation's price. The prompt may still be
 * empty, because the price is shown while the user is typing.
 */
class QuoteRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return GenerationRules::rules(promptRequired: false);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return GenerationRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return GenerationRules::attributes();
    }

    public function prompt(): string
    {
        return $this->string('prompt')->toString();
    }

    public function referenceImageCount(): int
    {
        return count($this->array('reference_images'));
    }
}
