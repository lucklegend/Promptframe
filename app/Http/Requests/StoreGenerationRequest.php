<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A generation the user is submitting: the same options as a quote, but
 * with a prompt this time.
 */
class StoreGenerationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return GenerationRules::rules(promptRequired: true);
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

    /**
     * @return list<string>
     */
    public function referenceImages(): array
    {
        return array_values(array_filter($this->array('reference_images'), is_string(...)));
    }
}
