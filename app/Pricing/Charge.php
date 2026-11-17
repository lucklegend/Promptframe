<?php

namespace App\Pricing;

/**
 * The final amounts for a succeeded generation: what Pixelforge charged us
 * and what we charge the user. The difference is our profit.
 */
final readonly class Charge
{
    public function __construct(
        public Nanodollars $providerCost,
        public Nanodollars $userCharge,
    ) {}
}
