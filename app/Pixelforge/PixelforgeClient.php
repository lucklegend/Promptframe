<?php

namespace App\Pixelforge;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use UnexpectedValueException;

/**
 * A thin wrapper over the Pixelforge HTTP API.
 *
 * It only knows how to talk to Pixelforge; what to do with the answers
 * (pricing, billing, retries) lives in the code that calls it.
 */
final readonly class PixelforgeClient
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private string $apiKey,
    ) {}

    /**
     * GET /v1/pricing. Public, so it needs no API key.
     *
     * @return array<string, mixed>
     */
    public function pricing(): array
    {
        return $this->json($this->request()->get('pricing')->throw()->json());
    }

    /**
     * GET /v1/usage: what Pixelforge billed this API key per UTC day.
     *
     * @return array<string, mixed>
     */
    public function usage(string $startDate, string $endDate): array
    {
        $response = $this->authenticated()
            ->get('usage', ['start_date' => $startDate, 'end_date' => $endDate])
            ->throw();

        return $this->json($response->json());
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    private function request(): PendingRequest
    {
        return $this->http->baseUrl($this->baseUrl)->acceptJson()->timeout(10);
    }

    private function authenticated(): PendingRequest
    {
        return $this->request()->withToken($this->apiKey);
    }

    /**
     * @return array<string, mixed>
     */
    private function json(mixed $body): array
    {
        if (! is_array($body)) {
            throw new UnexpectedValueException('Pixelforge returned a body that is not a JSON object.');
        }

        return $body;
    }
}
