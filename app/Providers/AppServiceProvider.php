<?php

namespace App\Providers;

use App\Pixelforge\PixelforgeClient;
use App\Pricing\CurrentPricing;
use App\Pricing\Markup;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Config is read here, once, and handed to the classes that need it,
        // so the classes themselves never reach into config() or env().
        $this->app->singleton(PixelforgeClient::class, fn (Application $app) => new PixelforgeClient(
            $app->make(HttpFactory::class),
            (string) config('services.pixelforge.base_url'),
            (string) config('services.pixelforge.api_key'),
        ));

        $this->app->singleton(Markup::class, fn () => new Markup((int) config('billing.markup_percent')));

        $this->app->singleton(CurrentPricing::class, fn (Application $app) => new CurrentPricing(
            $app->make(PixelforgeClient::class),
            $app->make('cache.store'),
            $app->make(Markup::class),
            (int) config('services.pixelforge.pricing_cache_seconds'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
