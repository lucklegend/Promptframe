<?php

namespace App\Http\Requests;

use App\Pixelforge\ImageQuality;
use App\Pixelforge\ImageSize;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The options that decide a generation's price, checked against the same
 * limits Pixelforge enforces so a quote is never given for a request
 * Pixelforge would reject.
 */
class QuoteRequest extends FormRequest
{
    public const MAX_PROMPT_CHARACTERS = 4000;

    public const MAX_REFERENCE_IMAGES = 4;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The prompt may still be empty while the user is typing.
            'prompt' => ['nullable', 'string', 'max:'.self::MAX_PROMPT_CHARACTERS],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['nullable', 'array', 'max:'.self::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reference_images.max' => 'Add at most '.self::MAX_REFERENCE_IMAGES.' reference images.',
            'reference_images.*.url' => 'Enter a full image address starting with http:// or https://.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'reference_images.*' => 'reference image',
        ];
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
