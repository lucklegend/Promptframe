<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class QuoteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function fakePricing(): void
    {
        Http::fake([
            'pixelforge.promptedit.dev/v1/pricing' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pixelforge/pricing.json')),
            ),
        ]);
    }

    public function test_quotes_the_worked_maximum_example_from_the_docs_with_markup(): void
    {
        $this->fakePricing();
        $response = $this->actingAs(User::factory()->create())->postJson('/generate/quote', [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
        ]);

        // Pixelforge's cost is $0.286225 to $0.373875; plus 25%.
        $response->assertOk()->assertExactJson([
            'min_charge' => '0.357781250',
            'max_charge' => '0.467343750',
        ]);
    }

    public function test_quotes_an_empty_prompt_while_the_user_is_still_typing(): void
    {
        $this->fakePricing();
        $this->actingAs(User::factory()->create())
            ->postJson('/generate/quote', ['prompt' => '', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertOk()
            ->assertJsonStructure(['min_charge', 'max_charge']);
    }

    public function test_caches_pricing_between_quotes(): void
    {
        $this->fakePricing();
        $user = User::factory()->create();
        $options = ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'medium'];

        $this->actingAs($user)->postJson('/generate/quote', $options)->assertOk();
        $this->actingAs($user)->postJson('/generate/quote', $options)->assertOk();

        Http::assertSentCount(1);
    }

    public function test_fetches_pricing_without_sending_the_api_key(): void
    {
        $this->fakePricing();
        config(['services.pixelforge.api_key' => 'pf_test_key']);

        $this->actingAs(User::factory()->create())
            ->postJson('/generate/quote', ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'medium'])
            ->assertOk();

        Http::assertSent(fn (Request $request) => ! $request->hasHeader('Authorization'));
    }

    public function test_rejects_options_pixelforge_would_reject(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/generate/quote', [
                'prompt' => str_repeat('a', 4001),
                'size' => '512x512',
                'quality' => 'ultra',
                'reference_images' => ['not a url', 'ftp://example.com/a.png', 'https://example.com/c.png', 'https://example.com/d.png', 'https://example.com/e.png'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prompt', 'size', 'quality', 'reference_images', 'reference_images.0', 'reference_images.1']);
    }

    public function test_counts_prompt_length_in_unicode_characters(): void
    {
        $this->fakePricing();
        // 4,000 characters is allowed even though it is 12,000 bytes.
        $this->actingAs(User::factory()->create())
            ->postJson('/generate/quote', ['prompt' => str_repeat('日', 4000), 'size' => '1024x1024', 'quality' => 'low'])
            ->assertOk();
    }

    public function test_explains_when_pricing_is_unavailable(): void
    {
        Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(['error' => []], 503)]);

        $this->actingAs(User::factory()->create())
            ->postJson('/generate/quote', ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'medium'])
            ->assertServiceUnavailable()
            ->assertJson(['message' => 'Prices are unavailable right now. Please try again in a moment.']);
    }

    public function test_guests_cannot_get_a_quote(): void
    {
        $this->postJson('/generate/quote', ['prompt' => 'a cat', 'size' => '1024x1024', 'quality' => 'medium'])
            ->assertUnauthorized();
    }
}
