<?php

namespace App\Pricing;

/**
 * What a generation will cost before it runs, as a range.
 *
 * The user is charged somewhere between minCharge and maxCharge once the
 * generation succeeds, and nothing if it fails. maxCharge is the amount to
 * hold against a balance, because the real charge can never exceed it.
 */
final readonly class Quote
{
    public function __construct(
        public UsageRange $usage,
        public Nanodollars $minProviderCost,
        public Nanodollars $maxProviderCost,
        public Nanodollars $minCharge,
        public Nanodollars $maxCharge,
    ) {}
}
