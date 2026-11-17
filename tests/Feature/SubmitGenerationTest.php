<?php

namespace Tests\Feature;

use App\Generations\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class SubmitGenerationTest extends TestCase
{
    use RefreshDatabase;

    private const RATES = [
        'input_text_tokens' => '5.00',
        'input_image_tokens' => '10.00',
        'output_tokens' => '40.00',
        'reasoning_tokens' => '12.50',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.pixelforge.api_key' => 'pf_test_key']);
        Http::preventStrayRequests();
    }

    /**
     * @return array<string, mixed>
     */
    private function accepted(string $status = 'queued'): array
    {
        return ['id' => 'gen_01TEST', 'object' => 'generation', 'status' => $status, 'rates' => self::RATES];
    }

    private function handle(SubmitGeneration $job): SubmitGeneration
    {
        $job->withFakeQueueInteractions()->handle($this->app->make(PixelforgeClient::class));

        return $job;
    }

    /**
     * @param  array<string, mixed>  $error
     */
    private function fakeError(int $status, array $error, array $headers = []): void
    {
        Http::fake(['*/v1/generations' => Http::response(['error' => $error], $status, $headers)]);
    }

    public function test_sends_the_generation_and_records_what_pixelforge_accepted(): void
    {
        Http::fake(['*/v1/generations' => Http::response($this->accepted(), 202)]);
        $generation = Generation::factory()->create(['reference_images' => ['https://example.com/a.png']]);

        $this->handle(new SubmitGeneration($generation))->assertNotReleased();

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer pf_test_key')
            && $request->hasHeader('Idempotency-Key', $generation->idempotency_key)
            && $request->data() === [
                'prompt' => $generation->prompt,
                'size' => '1024x1024',
                'quality' => 'medium',
                'reference_images' => ['https://example.com/a.png'],
                'metadata' => ['generation_id' => (string) $generation->id],
            ]);

        $generation->refresh();
        $this->assertSame(GenerationStatus::Queued, $generation->status);
        $this->assertSame('gen_01TEST', $generation->pixelforge_id);
        $this->assertSame(self::RATES, $generation->rates);
        $this->assertNotNull($generation->submitted_at);
    }

    public function test_running_twice_sends_the_same_idempotency_key_and_records_one_generation(): void
    {
        Http::fake(['*/v1/generations' => Http::response($this->accepted(), 202)]);
        $generation = Generation::factory()->create();

        // The first attempt reached Pixelforge, but the worker died before
        // the job was marked done, so the queue runs it again.
        $this->handle(new SubmitGeneration($generation));
        Generation::whereKey($generation->id)->update(['status' => GenerationStatus::Submitting->value, 'pixelforge_id' => null]);
        $this->handle(new SubmitGeneration($generation));

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Idempotency-Key', $generation->idempotency_key));
        $this->assertSame(1, Generation::count());
        $this->assertSame('gen_01TEST', $generation->fresh()->pixelforge_id);
    }

    public function test_does_nothing_once_the_generation_has_left_submitting(): void
    {
        Http::fake();
        $generation = Generation::factory()->status(GenerationStatus::Queued)->create();

        $this->handle(new SubmitGeneration($generation));

        Http::assertNothingSent();
    }

    public function test_a_webhook_that_arrives_before_the_response_is_not_undone(): void
    {
        $generation = Generation::factory()->create();
        Http::fake(function () use ($generation) {
            // While the POST is in flight, a webhook moves the row on.
            Generation::whereKey($generation->id)->update(['status' => GenerationStatus::Processing->value]);

            return Http::response($this->accepted('queued'), 202);
        });

        $this->handle(new SubmitGeneration($generation));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Processing, $generation->status);
        $this->assertSame('gen_01TEST', $generation->pixelforge_id);
        $this->assertSame(self::RATES, $generation->rates);
    }

    public function test_a_replayed_request_can_move_the_status_forward(): void
    {
        Http::fake(['*/v1/generations' => Http::response($this->accepted('processing'), 202, ['Idempotent-Replayed' => 'true'])]);
        $generation = Generation::factory()->create();

        $this->handle(new SubmitGeneration($generation));

        $this->assertSame(GenerationStatus::Processing, $generation->fresh()->status);
    }

    public function test_waits_for_retry_after_when_pixelforge_is_busy(): void
    {
        $this->fakeError(429, ['type' => 'rate_limit_error', 'code' => 'concurrency_limit_exceeded', 'message' => 'Busy.'], ['Retry-After' => '5']);
        $generation = Generation::factory()->create();

        $this->handle(new SubmitGeneration($generation))->assertReleased(5);

        $this->assertSame(GenerationStatus::Submitting, $generation->fresh()->status);
    }

    public function test_waits_for_retry_after_when_pixelforge_is_overloaded(): void
    {
        $this->fakeError(503, ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.'], ['Retry-After' => '2']);
        $generation = Generation::factory()->create();

        $this->handle(new SubmitGeneration($generation))->assertReleased(2);

        $this->assertSame(GenerationStatus::Submitting, $generation->fresh()->status);
    }

    public function test_a_rejected_prompt_fails_and_is_free(): void
    {
        $this->fakeError(400, ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'Rejected.']);
        $generation = Generation::factory()->create();

        $this->handle(new SubmitGeneration($generation))->assertNotReleased();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('content_policy_violation', $generation->error_code);
        $this->assertSame('Pixelforge rejected this prompt. Try wording it differently.', $generation->error_message);
        $this->assertSame(0, $generation->charge);
        $this->assertSame(0, $generation->provider_cost);
        $this->assertNotNull($generation->completed_at);
    }

    public function test_running_out_of_the_daily_quota_fails_instead_of_retrying(): void
    {
        $this->fakeError(429, ['type' => 'rate_limit_error', 'code' => 'daily_limit_exceeded', 'message' => 'Quota used.'], ['Retry-After' => '3600']);
        $generation = Generation::factory()->create();

        $this->handle(new SubmitGeneration($generation))->assertNotReleased();

        $this->assertSame(GenerationStatus::Failed, $generation->fresh()->status);
        $this->assertSame('daily_limit_exceeded', $generation->fresh()->error_code);
    }

    public function test_giving_up_marks_the_generation_failed_and_free(): void
    {
        $generation = Generation::factory()->create();

        (new SubmitGeneration($generation))->failed(new RuntimeException('Connection refused'));

        $generation->refresh();
        $this->assertSame(GenerationStatus::Failed, $generation->status);
        $this->assertSame('submit_failed', $generation->error_code);
        $this->assertSame(0, $generation->charge);
    }

    public function test_giving_up_never_overwrites_a_generation_pixelforge_already_finished(): void
    {
        $generation = Generation::factory()->status(GenerationStatus::Succeeded)->create();

        (new SubmitGeneration($generation))->failed(new RuntimeException('Late failure'));

        $this->assertSame(GenerationStatus::Succeeded, $generation->fresh()->status);
    }
}
