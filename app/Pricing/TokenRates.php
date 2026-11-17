<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * Pixelforge's price per 1M tokens for each token type, in US dollars.
 *
 * A generation is billed at the rates in force when it was created, and
 * Pixelforge returns those rates on the generation itself, so we keep one
 * TokenRates per generation rather than one global price list.
 */
final readonly class TokenRates
{
    private const TOKENS_PER_RATE = '1000000';

    /**
     * @param  array<string, string>  $perMillion  Decimal dollar strings keyed by token type.
     */
    private function __construct(private array $perMillion) {}

    /**
     * Build from a Pixelforge `rates` object, e.g. {"output_tokens": "40.00", ...}.
     *
     * @param  array<string, mixed>  $rates
     */
    public static function fromArray(array $rates): self
    {
        $perMillion = [];

        foreach (TokenUsage::TYPES as $type) {
            $rate = $rates[$type] ?? null;

            if (! is_string($rate) || ! preg_match('/^\d+(\.\d+)?$/', $rate)) {
                throw new InvalidArgumentException("Rate for {$type} must be a decimal string.");
            }

            $perMillion[$type] = $rate;
        }

        return new self($perMillion);
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return $this->perMillion;
    }

    /**
     * What Pixelforge charges for this usage.
     *
     * The sum is computed exactly with bcmath. With rates of up to three
     * decimal places the result is always a whole number of nanodollars;
     * any finer remainder is rounded half up to match a nine-place total.
     */
    public function costOf(TokenUsage $usage): Nanodollars
    {
        $tokenDollars = '0';

        foreach ($usage->toArray() as $type => $count) {
            $tokenDollars = bcadd($tokenDollars, bcmul((string) $count, $this->perMillion[$type], 18), 18);
        }

        $dollars = bcdiv($tokenDollars, self::TOKENS_PER_RATE, 18);

        // bcadd truncates to the requested scale, so adding half a
        // nanodollar first turns that truncation into round-half-up.
        return Nanodollars::fromDecimalString(bcadd($dollars, '0.0000000005', 9));
    }
}
