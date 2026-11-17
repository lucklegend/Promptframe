<?php

namespace App\Pricing;

use InvalidArgumentException;

/**
 * An exact, non-negative amount of US dollars held as whole nanodollars.
 *
 * 1 USD = 1,000,000,000 nanodollars. Pixelforge reports money as decimal
 * strings with nine places, so every amount it bills fits exactly in an
 * integer and no floating-point rounding can creep in.
 */
final readonly class Nanodollars
{
    public const PER_DOLLAR = 1_000_000_000;

    public function __construct(public int $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException("Money cannot be negative, got {$amount} nanodollars.");
        }
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Parse a decimal dollar string such as "0.046125000".
     */
    public static function fromDecimalString(string $dollars): self
    {
        if (! preg_match('/^(\d{1,9})(?:\.(\d{1,9}))?$/', $dollars, $matches)) {
            throw new InvalidArgumentException(
                "Expected a dollar amount with at most nine decimal places, got \"{$dollars}\"."
            );
        }

        $whole = (int) $matches[1];
        $fraction = (int) str_pad($matches[2] ?? '', 9, '0');

        return new self($whole * self::PER_DOLLAR + $fraction);
    }

    /**
     * Format with all nine decimal places, the same way Pixelforge does.
     */
    public function toDecimalString(): string
    {
        $whole = intdiv($this->amount, self::PER_DOLLAR);
        $fraction = str_pad((string) ($this->amount % self::PER_DOLLAR), 9, '0', STR_PAD_LEFT);

        return "{$whole}.{$fraction}";
    }

    public function plus(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount > $other->amount;
    }
}
