<?php

namespace App\Pricing;

use App\Pixelforge\PixelforgeClient;
use Illuminate\Contracts\Cache\Repository as Cache;
use UnexpectedValueException;

/**
 * Builds a PriceCalculator from Pixelforge's published prices.
 *
 * Prices come from GET /v1/pricing rather than constants, so a price change
 * on Pixelforge's side needs no deploy. The response is cached briefly so a
 * user typing a prompt doesn't send a pricing request on every keystroke.
 */
final readonly class CurrentPricing
{
    private const CACHE_KEY = 'pixelforge.pricing';

    public function __construct(
        private PixelforgeClient $pixelforge,
        private Cache $cache,
        private Markup $markup,
        private int $cacheSeconds,
    ) {}

    public function calculator(): PriceCalculator
    {
        $pricing = $this->cache->remember(self::CACHE_KEY, $this->cacheSeconds, $this->pixelforge->pricing(...));

        if (! is_array($pricing['tokens'] ?? null) || ! is_array($pricing['rates'] ?? null)) {
            throw new UnexpectedValueException('Pixelforge pricing is missing its tokens or rates.');
        }

        return new PriceCalculator(
            TokenTable::fromArray($pricing['tokens']),
            TokenRates::fromArray($pricing['rates']),
            $this->markup,
        );
    }
}
