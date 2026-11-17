<?php

namespace Tests\Unit\Pricing;

use App\Pricing\Nanodollars;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NanodollarsTest extends TestCase
{
    public function test_parses_pixelforge_decimal_strings_exactly(): void
    {
        $this->assertSame(46_125_000, Nanodollars::fromDecimalString('0.046125000')->amount);
        $this->assertSame(1_000_000_000, Nanodollars::fromDecimalString('1')->amount);
        $this->assertSame(500_000_000, Nanodollars::fromDecimalString('0.5')->amount);
        $this->assertSame(1, Nanodollars::fromDecimalString('0.000000001')->amount);
    }

    public function test_formats_with_nine_decimal_places(): void
    {
        $this->assertSame('0.373875000', (new Nanodollars(373_875_000))->toDecimalString());
        $this->assertSame('12.000000001', (new Nanodollars(12_000_000_001))->toDecimalString());
        $this->assertSame('0.000000000', Nanodollars::zero()->toDecimalString());
    }

    public function test_round_trips_through_a_decimal_string(): void
    {
        $amount = Nanodollars::fromDecimalString('0.169080000');

        $this->assertSame('0.169080000', $amount->toDecimalString());
    }

    public function test_adds_and_compares(): void
    {
        $sum = (new Nanodollars(250))->plus(new Nanodollars(750));

        $this->assertSame(1000, $sum->amount);
        $this->assertTrue($sum->isGreaterThan(new Nanodollars(999)));
        $this->assertFalse($sum->isGreaterThan(new Nanodollars(1000)));
    }

    /** @return array<string, array{string}> */
    public static function invalidAmounts(): array
    {
        return [
            'negative' => ['-1.00'],
            'ten decimal places' => ['0.0000000001'],
            'scientific notation' => ['1e-9'],
            'not a number' => ['abc'],
            'empty' => [''],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_rejects_amounts_it_cannot_hold_exactly(string $dollars): void
    {
        $this->expectException(InvalidArgumentException::class);

        Nanodollars::fromDecimalString($dollars);
    }

    public function test_rejects_negative_amounts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Nanodollars(-1);
    }
}
