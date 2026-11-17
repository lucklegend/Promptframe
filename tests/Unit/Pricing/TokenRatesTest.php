<?php

namespace Tests\Unit\Pricing;

use App\Pricing\TokenRates;
use App\Pricing\TokenUsage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TokenRatesTest extends TestCase
{
    private TokenRates $rates;

    protected function setUp(): void
    {
        $this->rates = TokenRates::fromArray([
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ]);
    }

    public function test_costs_the_succeeded_generation_from_the_docs(): void
    {
        $usage = TokenUsage::fromArray([
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ]);

        // 12×5 + 765×10 + 6450×40 + 3184×12.5 = 305,510 dollar-tokens per 1M.
        $this->assertSame('0.305510000', $this->rates->costOf($usage)->toDecimalString());
    }

    public function test_matches_the_usage_endpoint_example_from_the_docs(): void
    {
        $usage = new TokenUsage(inputText: 41, inputImage: 0, output: 3300, reasoning: 2950);

        $this->assertSame('0.169080000', $this->rates->costOf($usage)->toDecimalString());
    }

    public function test_matches_the_worked_maximum_example_from_the_docs(): void
    {
        $usage = new TokenUsage(inputText: 115, inputImage: 1530, output: 6450, reasoning: 8000);

        $this->assertSame('0.373875000', $this->rates->costOf($usage)->toDecimalString());
    }

    public function test_one_token_at_one_dollar_per_million_is_one_microdollar(): void
    {
        $rates = TokenRates::fromArray(array_fill_keys(TokenUsage::TYPES, '1.00'));
        $usage = new TokenUsage(inputText: 1, inputImage: 0, output: 0, reasoning: 0);

        $this->assertSame(1_000, $rates->costOf($usage)->amount);
    }

    public function test_rounds_a_half_nanodollar_up(): void
    {
        // $0.0005 per 1M tokens makes one token cost half a nanodollar.
        $rates = TokenRates::fromArray(array_fill_keys(TokenUsage::TYPES, '0.0005'));
        $usage = new TokenUsage(inputText: 1, inputImage: 0, output: 0, reasoning: 0);

        $this->assertSame(1, $rates->costOf($usage)->amount);
    }

    public function test_rejects_a_float_rate(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TokenRates::fromArray([
            'input_text_tokens' => 5.0,
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ]);
    }

    public function test_rejects_usage_missing_a_token_type(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TokenUsage::fromArray(['input_text_tokens' => 12, 'output_tokens' => 6450]);
    }
}
