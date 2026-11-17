<?php

namespace Tests\Unit\Pricing;

use App\Pricing\Markup;
use App\Pricing\Nanodollars;
use PHPUnit\Framework\TestCase;

class MarkupTest extends TestCase
{
    public function test_adds_twenty_five_percent(): void
    {
        $charge = (new Markup(25))->apply(Nanodollars::fromDecimalString('0.305510000'));

        $this->assertSame('0.381887500', $charge->toDecimalString());
    }

    public function test_rounds_a_fraction_of_a_nanodollar_up(): void
    {
        $markup = new Markup(25);

        // 1 × 1.25 = 1.25, 2 × 1.25 = 2.5, 3 × 1.25 = 3.75: all round up.
        $this->assertSame(2, $markup->apply(new Nanodollars(1))->amount);
        $this->assertSame(3, $markup->apply(new Nanodollars(2))->amount);
        $this->assertSame(4, $markup->apply(new Nanodollars(3))->amount);
        $this->assertSame(5, $markup->apply(new Nanodollars(4))->amount);
    }

    public function test_a_free_generation_stays_free(): void
    {
        $this->assertSame(0, (new Markup(25))->apply(Nanodollars::zero())->amount);
    }
}
