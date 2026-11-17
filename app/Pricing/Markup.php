<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * Turns Pixelforge's cost into what we charge the user.
 */
final readonly class Markup
{
    public function __construct(public int $percent)
    {
        if ($percent < 0) {
            throw new InvalidArgumentException("Markup cannot be negative, got {$percent}%.");
        }
    }

    /**
     * Add the markup, rounding any fraction of a nanodollar up.
     *
     * Rounding up means we never charge less than cost plus markup; the
     * most a user can overpay is one nanodollar ($0.000000001) per generation.
     */
    public function apply(Nanodollars $cost): Nanodollars
    {
        $hundredths = $cost->amount * (100 + $this->percent);

        return new Nanodollars(intdiv($hundredths + 99, 100));
    }
}
