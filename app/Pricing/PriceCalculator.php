<?php

namespace App\Pricing;

/**
 * The one place that decides what a generation costs.
 *
 * It quotes a range before a generation runs and charges the exact
 * amount after, so the estimate and the final price can never disagree
 * about how the markup or rounding works.
 */
final readonly class PriceCalculator
{
    public function __construct(
        private TokenTable $tokens,
        private TokenRates $currentRates,
        private Markup $markup,
    ) {}

    public function quote(string $prompt, string $size, string $quality, int $referenceImages): Quote
    {
        $usage = $this->tokens->usageRange($prompt, $size, $quality, $referenceImages);
        $minCost = $this->currentRates->costOf($usage->min);
        $maxCost = $this->currentRates->costOf($usage->max);

        return new Quote(
            usage: $usage,
            minProviderCost: $minCost,
            maxProviderCost: $maxCost,
            minCharge: $this->markup->apply($minCost),
            maxCharge: $this->markup->apply($maxCost),
        );
    }

    /**
     * The final price of a succeeded generation, at the rates Pixelforge
     * fixed when that generation was created.
     */
    public function charge(TokenUsage $usage, TokenRates $ratesAtCreation): Charge
    {
        $cost = $ratesAtCreation->costOf($usage);

        return new Charge(providerCost: $cost, userCharge: $this->markup->apply($cost));
    }
}
