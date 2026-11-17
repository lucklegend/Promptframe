<?php

namespace Tests\Feature;

use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StoreGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        Queue::fake();
    }

    private function fakePricing(): void
    {
        Http::fake([
            'pixelforge.promptedit.dev/v1/pricing' => Http::response(
                file_get_contents(base_path('tests/Fixtures/pixelforge/pricing.json')),
            ),
        ]);
    }

    public function test_saves_the_generation_with_its_estimate_and_queues_it(): void
    {
        $this->fakePricing();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/generations', [
            'prompt' => str_repeat('a', 400),
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/a.png', 'https://example.com/b.png'],
        ]);

        $response->assertStatus(202)->assertJson(['status' => 'submitting']);

        $generation = Generation::sole();
        $this->assertTrue($generation->user->is($user));
        $this->assertSame(GenerationStatus::Submitting, $generation->status);
        $this->assertSame(['https://example.com/a.png', 'https://example.com/b.png'], $generation->reference_images);
        // The same range the quote showed: the docs' worked example plus 25%.
        $this->assertSame(357_781_250, $generation->estimated_min_charge);
        $this->assertSame(467_343_750, $generation->estimated_max_charge);
        $this->assertNotEmpty($generation->idempotency_key);
        $this->assertNull($generation->charge);

        Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job) => $job->generation->is($generation));
    }

    public function test_requires_a_prompt(): void
    {
        $this->fakePricing();

        $this->actingAs(User::factory()->create())
            ->postJson('/generations', ['prompt' => '   ', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['prompt' => 'Describe the image you want.']);

        $this->assertDatabaseEmpty('generations');
        Queue::assertNothingPushed();
    }

    public function test_rejects_options_pixelforge_would_reject(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson('/generations', [
                'prompt' => 'A fox',
                'size' => '512x512',
                'quality' => 'ultra',
                'reference_images' => ['not-a-url'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['size', 'quality', 'reference_images.0']);

        $this->assertDatabaseEmpty('generations');
    }

    public function test_saves_nothing_when_prices_are_unavailable(): void
    {
        Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(null, 500)]);

        $this->actingAs(User::factory()->create())
            ->postJson('/generations', ['prompt' => 'A fox', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertStatus(503);

        $this->assertDatabaseEmpty('generations');
        Queue::assertNothingPushed();
    }

    public function test_guests_cannot_submit(): void
    {
        $this->postJson('/generations', ['prompt' => 'A fox', 'size' => '1024x1024', 'quality' => 'low'])
            ->assertUnauthorized();
    }
}
