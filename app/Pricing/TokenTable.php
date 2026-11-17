<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * How many tokens a request will use, from the `tokens` section of
 * GET /v1/pricing.
 *
 * Image and output tokens are exact. Text tokens are an estimate within a
 * published variance, and reasoning tokens fall in a range per quality, so
 * before a generation runs we can only give the fewest and the most.
 */
final readonly class TokenTable
{
    private const BASIS_POINTS = 10_000;

    /**
     * @param  array<string, array<string, int>>  $outputTokens  quality => size => tokens
     * @param  array<string, array{min: int, max: int}>  $reasoningTokens  quality => range
     */
    private function __construct(
        private int $charactersPerTextToken,
        private int $textVarianceBasisPoints,
        private int $tokensPerReferenceImage,
        private array $outputTokens,
        private array $reasoningTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $tokens  The `tokens` object from GET /v1/pricing.
     */
    public static function fromArray(array $tokens): self
    {
        $variance = $tokens['text_token_variance'] ?? null;

        if (! is_int($variance) && ! is_float($variance)) {
            throw new InvalidArgumentException('text_token_variance must be a number.');
        }

        return new self(
            charactersPerTextToken: self::positiveInt($tokens, 'characters_per_text_token'),
            // The variance arrives as a fraction such as 0.15. Holding it
            // as whole basis points (1500) keeps the arithmetic in integers.
            textVarianceBasisPoints: (int) round($variance * self::BASIS_POINTS),
            tokensPerReferenceImage: self::positiveInt($tokens, 'per_reference_image'),
            outputTokens: $tokens['output'] ?? [],
            reasoningTokens: $tokens['reasoning'] ?? [],
        );
    }

    public function usageRange(string $prompt, string $size, string $quality, int $referenceImages): UsageRange
    {
        $output = $this->outputTokens[$quality][$size]
            ?? throw new InvalidArgumentException("No output token count for {$quality} quality at {$size}.");
        $reasoning = $this->reasoningTokens[$quality]
            ?? throw new InvalidArgumentException("No reasoning range for {$quality} quality.");

        // The text bounds round outward (floor the minimum, ceil the
        // maximum), so the range always contains the real count.
        $text = $this->estimatedTextTokens($prompt);
        $imageTokens = $referenceImages * $this->tokensPerReferenceImage;

        return new UsageRange(
            min: new TokenUsage(
                inputText: intdiv($text * (self::BASIS_POINTS - $this->textVarianceBasisPoints), self::BASIS_POINTS),
                inputImage: $imageTokens,
                output: $output,
                reasoning: $reasoning['min'],
            ),
            max: new TokenUsage(
                inputText: self::ceilDiv($text * (self::BASIS_POINTS + $this->textVarianceBasisPoints), self::BASIS_POINTS),
                inputImage: $imageTokens,
                output: $output,
                reasoning: $reasoning['max'],
            ),
        );
    }

    /**
     * Pixelforge counts Unicode characters, not bytes, so "日本" is 2.
     */
    private function estimatedTextTokens(string $prompt): int
    {
        return self::ceilDiv(mb_strlen($prompt, 'UTF-8'), $this->charactersPerTextToken);
    }

    private static function ceilDiv(int $dividend, int $divisor): int
    {
        return intdiv($dividend + $divisor - 1, $divisor);
    }

    /**
     * @param  array<string, mixed>  $tokens
     */
    private static function positiveInt(array $tokens, string $key): int
    {
        $value = $tokens[$key] ?? null;

        if (! is_int($value) || $value < 1) {
            throw new InvalidArgumentException("{$key} must be a positive integer.");
        }

        return $value;
    }
}
