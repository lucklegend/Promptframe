<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * Token counts for one generation, in the shape Pixelforge reports them.
 */
final readonly class TokenUsage
{
    /** The billable token types, keyed as Pixelforge names them. */
    public const TYPES = ['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens'];

    public function __construct(
        public int $inputText,
        public int $inputImage,
        public int $output,
        public int $reasoning,
    ) {
        foreach ($this->toArray() as $type => $count) {
            if ($count < 0) {
                throw new InvalidArgumentException("{$type} cannot be negative, got {$count}.");
            }
        }
    }

    /**
     * Build from a Pixelforge `usage` object. Extra keys such as
     * `total_tokens` are ignored because they are derived, not billed.
     *
     * @param  array<string, mixed>  $usage
     */
    public static function fromArray(array $usage): self
    {
        foreach (self::TYPES as $type) {
            if (! is_int($usage[$type] ?? null)) {
                throw new InvalidArgumentException("Usage is missing an integer {$type}.");
            }
        }

        return new self(
            inputText: $usage['input_text_tokens'],
            inputImage: $usage['input_image_tokens'],
            output: $usage['output_tokens'],
            reasoning: $usage['reasoning_tokens'],
        );
    }

    /**
     * @return array<string, int>
     */
    public function toArray(): array
    {
        return [
            'input_text_tokens' => $this->inputText,
            'input_image_tokens' => $this->inputImage,
            'output_tokens' => $this->output,
            'reasoning_tokens' => $this->reasoning,
        ];
    }
}
