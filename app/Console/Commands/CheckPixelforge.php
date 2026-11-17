<?php

namespace App\Console\Commands;

use App\Pixelforge\PixelforgeClient;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;

/**
 * Checks the Pixelforge settings in .env without ever printing them.
 */
class CheckPixelforge extends Command
{
    protected $signature = 'pixelforge:check';

    protected $description = 'Check that the Pixelforge API key works and a webhook secret is set';

    public function handle(PixelforgeClient $pixelforge): int
    {
        $keyOk = $this->checkApiKey($pixelforge);
        $secretOk = $this->checkWebhookSecret((string) config('services.pixelforge.webhook_secret'));

        return $keyOk && $secretOk ? self::SUCCESS : self::FAILURE;
    }

    private function checkApiKey(PixelforgeClient $pixelforge): bool
    {
        if (! $pixelforge->hasApiKey()) {
            $this->components->error('PIXELFORGE_API_KEY is not set in .env.');

            return false;
        }

        $today = now('UTC')->toDateString();

        try {
            // Usage is a harmless read that needs a valid key.
            $pixelforge->usage($today, $today);
        } catch (RequestException $exception) {
            $this->components->error($exception->response->status() === 401
                ? 'Pixelforge rejected PIXELFORGE_API_KEY (401). Check it was copied in full.'
                : "Pixelforge answered {$exception->response->status()} when checking the key.");

            return false;
        } catch (ConnectionException) {
            $this->components->error('Could not reach Pixelforge. Check your internet connection.');

            return false;
        }

        $this->components->info('PIXELFORGE_API_KEY works.');

        return true;
    }

    private function checkWebhookSecret(string $secret): bool
    {
        if (! str_starts_with($secret, 'whsec_')) {
            $this->components->error($secret === ''
                ? 'PIXELFORGE_WEBHOOK_SECRET is not set in .env.'
                : 'PIXELFORGE_WEBHOOK_SECRET should start with whsec_.');

            return false;
        }

        $this->components->info('PIXELFORGE_WEBHOOK_SECRET is set.');

        return true;
    }
}
