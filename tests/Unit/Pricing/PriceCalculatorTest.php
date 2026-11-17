<?php

namespace Tests\Unit\Pricing;

use App\Pricing\Markup;
use App\Pricing\PriceCalculator;
use App\Pricing\TokenRates;
use App\Pricing\TokenTable;
use App\Pricing\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        $pricing = json_decode(
            file_get_contents(__DIR__.'/../../Fixtures/pixelforge/pricing.json'),
            associative: true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->calculator = new PriceCalculator(
            TokenTable::fromArray($pricing['tokens']),
            TokenRates::fromArray($pricing['rates']),
            new Markup(25),
        );
    }

    public function test_quotes_the_worked_maximum_example_from_the_docs(): void
    {
        $quote = $this->calculator->quote(str_repeat('a', 400), '1536x1024', 'high', referenceImages: 2);

        $this->assertEquals(
            new TokenUsage(inputText: 115, inputImage: 1530, output: 6450, reasoning: 8000),
            $quote->usage->max,
        );
        $this->assertSame('0.373875000', $quote->maxProviderCost->toDecimalString());
        $this->assertSame('0.467343750', $quote->maxCharge->toDecimalString());
    }

    public function test_quotes_the_fewest_tokens_the_same_request_can_use(): void
    {
        $quote = $this->calculator->quote(str_repeat('a', 400), '1536x1024', 'high', referenceImages: 2);

        $this->assertEquals(
            new TokenUsage(inputText: 85, inputImage: 1530, output: 6450, reasoning: 1000),
            $quote->usage->min,
        );
        // 85×5 + 1530×10 + 6450×40 + 1000×12.5 = 286,225 dollar-tokens per 1M.
        $this->assertSame('0.286225000', $quote->minProviderCost->toDecimalString());
    }

    public function test_counts_unicode_characters_not_bytes(): void
    {
        // Eight characters but 24 bytes in UTF-8: estimate ceil(8 / 4) = 2 tokens,
        // so ±15% is 1.7 to 2.3. Counting bytes would have estimated 6.
        $quote = $this->calculator->quote('日本語の絵を描く', '1024x1024', 'low', referenceImages: 0);

        $this->assertSame(1, $quote->usage->min->inputText);
        $this->assertSame(3, $quote->usage->max->inputText);
    }

    public function test_rounds_the_text_token_bounds_outward(): void
    {
        // 25 characters: estimate 7 tokens, ±15% is 5.95 to 8.05.
        $quote = $this->calculator->quote(str_repeat('a', 25), '1024x1024', 'medium', referenceImages: 0);

        $this->assertSame(5, $quote->usage->min->inputText);
        $this->assertSame(9, $quote->usage->max->inputText);
    }

    public function test_charges_the_succeeded_generation_from_the_docs_at_its_own_rates(): void
    {
        $ratesAtCreation = TokenRates::fromArray([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ]);
        $usage = new TokenUsage(inputText: 12, inputImage: 765, output: 6450, reasoning: 3184);

        $charge = $this->calculator->charge($usage, $ratesAtCreation);

        $this->assertSame('0.305510000', $charge->providerCost->toDecimalString());
        $this->assertSame('0.381887500', $charge->userCharge->toDecimalString());
    }

    public function test_charges_at_the_creation_rates_even_after_prices_change(): void
    {
        $oldRates = TokenRates::fromArray(array_fill_keys(TokenUsage::TYPES, '1.00'));
        $usage = new TokenUsage(inputText: 0, inputImage: 0, output: 1_000_000, reasoning: 0);

        $charge = $this->calculator->charge($usage, $oldRates);

        $this->assertSame('1.000000000', $charge->providerCost->toDecimalString());
    }

    public function test_the_actual_charge_never_exceeds_the_quoted_maximum(): void
    {
        $quote = $this->calculator->quote(str_repeat('a', 400), '1536x1024', 'high', referenceImages: 2);
        $rates = TokenRates::fromArray([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ]);
        $charge = $this->calculator->charge($quote->usage->max, $rates);

        $this->assertFalse($charge->userCharge->isGreaterThan($quote->maxCharge));
    }

    public function test_rejects_a_size_pixelforge_does_not_offer(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->calculator->quote('a cat', '512x512', 'high', referenceImages: 0);
    }
}
