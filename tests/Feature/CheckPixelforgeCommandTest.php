<?php

namespace Tests\Feature;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CheckPixelforgeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config([
            'services.pixelforge.api_key' => 'pf_test_key',
            'services.pixelforge.webhook_secret' => 'whsec_test_secret',
        ]);
    }

    public function test_passes_when_the_key_works_and_the_secret_is_set(): void
    {
        Http::fake(['pixelforge.promptedit.dev/v1/usage*' => Http::response(['object' => 'usage', 'data' => []])]);

        $this->artisan('pixelforge:check')
            ->expectsOutputToContain('PIXELFORGE_API_KEY works.')
            ->expectsOutputToContain('PIXELFORGE_WEBHOOK_SECRET is set.')
            ->doesntExpectOutputToContain('pf_test_key')
            ->assertSuccessful();

        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer pf_test_key'));
    }

    public function test_fails_when_pixelforge_rejects_the_key(): void
    {
        Http::fake(['pixelforge.promptedit.dev/v1/usage*' => Http::response(['error' => ['code' => 'invalid_api_key']], 401)]);

        $this->artisan('pixelforge:check')
            ->expectsOutputToContain('Pixelforge rejected PIXELFORGE_API_KEY (401)')
            ->assertFailed();
    }

    public function test_fails_when_nothing_is_configured(): void
    {
        config(['services.pixelforge.api_key' => null, 'services.pixelforge.webhook_secret' => null]);

        $this->artisan('pixelforge:check')
            ->expectsOutputToContain('PIXELFORGE_API_KEY is not set in .env.')
            ->expectsOutputToContain('PIXELFORGE_WEBHOOK_SECRET is not set in .env.')
            ->assertFailed();

        Http::assertNothingSent();
    }
}
