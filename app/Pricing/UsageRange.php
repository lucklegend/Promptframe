<?php

namespace App\Pricing;

/**
 * The fewest and the most tokens a generation can use before it runs.
 */
final readonly class UsageRange
{
    public function __construct(
        public TokenUsage $min,
        public TokenUsage $max,
    ) {}
}
