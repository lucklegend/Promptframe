<?php

namespace App\Http\Requests;

use App\Pixelforge\ImageQuality;
use App\Pixelforge\ImageSize;
use Illuminate\Validation\Rule;

/**
 * The validation shared by pricing a generation and submitting one, checked
 * against the same limits Pixelforge enforces. Keeping it in one place means
 * we never quote a request we would then refuse to send.
 */
final class GenerationRules
{
    public const MAX_PROMPT_CHARACTERS = 4000;

    public const MAX_REFERENCE_IMAGES = 4;

    /**
     * @return array<string, mixed>
     */
    public static function rules(bool $promptRequired): array
    {
        return [
            'prompt' => [$promptRequired ? 'required' : 'nullable', 'string', 'max:'.self::MAX_PROMPT_CHARACTERS],
            'size' => ['required', Rule::enum(ImageSize::class)],
            'quality' => ['required', Rule::enum(ImageQuality::class)],
            'reference_images' => ['nullable', 'array', 'max:'.self::MAX_REFERENCE_IMAGES],
            'reference_images.*' => ['required', 'string', 'url:http,https', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'prompt.required' => 'Describe the image you want.',
            'reference_images.max' => 'Add at most '.self::MAX_REFERENCE_IMAGES.' reference images.',
            'reference_images.*.url' => 'Enter a full image address starting with http:// or https://.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return [
            'reference_images.*' => 'reference image',
        ];
    }
}
