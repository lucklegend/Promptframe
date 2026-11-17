<?php

namespace App\Jobs;

use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Pixelforge\PixelforgeClient;
use App\Pricing\TokenRates;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;
use UnexpectedValueException;

/**
 * Sends a saved generation to Pixelforge.
 *
 * It runs on the queue so Pixelforge being busy never holds up a page.
 * Every attempt sends the same Idempotency-Key, so however many times this
 * job runs, Pixelforge creates (and bills) at most one image.
 */
final class SubmitGeneration implements ShouldQueue
{
    use Queueable;

    /** Retry as often as needed; retryUntil() sets the real limit. */
    public int $tries = 0;

    private const DEFAULT_RETRY_SECONDS = 5;

    private const MAX_RETRY_SECONDS = 60;

    public function __construct(public Generation $generation) {}

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(15);
    }

    /**
     * Seconds to wait after a network error before trying again.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [5, 15, 30, 60];
    }

    public function handle(PixelforgeClient $pixelforge): void
    {
        $generation = $this->generation->fresh();

        // Already accepted (or failed) on an earlier attempt: nothing to send.
        if ($generation === null || $generation->status !== GenerationStatus::Submitting) {
            return;
        }

        try {
            $accepted = $pixelforge->createGeneration([
                'prompt' => $generation->prompt,
                'size' => $generation->size,
                'quality' => $generation->quality,
                'reference_images' => array_values($generation->reference_images),
                // Echoed back in every webhook, so we can find this row again.
                'metadata' => ['generation_id' => (string) $generation->getKey()],
            ], $generation->idempotency_key);
        } catch (RequestException $exception) {
            $this->handleError($generation, $exception->response);

            return;
        }

        $this->recordAccepted($generation, $accepted);
    }

    /**
     * Retries ran out, or something unexpected kept failing.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Gave up submitting a generation to Pixelforge.', [
            'generation_id' => $this->generation->getKey(),
            'error' => $exception?->getMessage(),
        ]);

        $this->generation->markFailed('submit_failed', "We couldn't reach Pixelforge, so this image wasn't made. You weren't charged.");
    }

    /**
     * @param  array<string, mixed>  $accepted
     */
    private function recordAccepted(Generation $generation, array $accepted): void
    {
        $id = $accepted['id'] ?? null;
        $rates = $accepted['rates'] ?? null;
        $status = is_string($accepted['status'] ?? null) ? GenerationStatus::tryFrom($accepted['status']) : null;

        if (! is_string($id) || ! str_starts_with($id, 'gen_') || ! is_array($rates)) {
            throw new UnexpectedValueException('Pixelforge accepted the generation without an id or rates.');
        }

        $generation->recordAccepted(
            $id,
            // Parsing checks every rate is present and well formed.
            TokenRates::fromArray($rates)->toArray(),
            $status === null || $status === GenerationStatus::Submitting ? GenerationStatus::Queued : $status,
        );
    }

    private function handleError(Generation $generation, Response $response): void
    {
        $code = $response->json('error.code');
        $code = is_string($code) ? $code : 'http_'.$response->status();

        if ($this->isTemporary($response->status(), $code)) {
            $this->release($this->retryAfter($response));

            return;
        }

        Log::warning('Pixelforge rejected a generation.', [
            'generation_id' => $generation->getKey(),
            'status' => $response->status(),
            'code' => $code,
        ]);

        $generation->markFailed($code, $this->messageFor($code, $response));
    }

    /**
     * Busy and overloaded are worth waiting out. Running out of the daily
     * quota is not: it won't clear for hours.
     */
    private function isTemporary(int $status, string $code): bool
    {
        return ($status === 429 && $code !== 'daily_limit_exceeded') || $status >= 500;
    }

    private function retryAfter(Response $response): int
    {
        $seconds = (int) $response->header('Retry-After');

        return $seconds > 0 ? min($seconds, self::MAX_RETRY_SECONDS) : self::DEFAULT_RETRY_SECONDS;
    }

    private function messageFor(string $code, Response $response): string
    {
        $message = $response->json('error.message');

        return match ($code) {
            'content_policy_violation' => 'Pixelforge rejected this prompt. Try wording it differently.',
            'daily_limit_exceeded' => "Today's image limit is used up. Please try again after midnight UTC.",
            // Our key or request is wrong; the user can't fix that.
            'invalid_api_key', 'idempotency_key_reused' => 'Image generation is unavailable right now.',
            default => is_string($message) ? $message : 'Pixelforge could not make this image.',
        };
    }
}
