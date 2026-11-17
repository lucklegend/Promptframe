<?php

namespace Tests\Unit\Pricing;

use App\Pixelforge\PixelforgeClient;
use App\Pricing\CurrentPricing;
use App\Pricing\Markup;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Http\Client\Factory as HttpFactory;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class CurrentPricingTest extends TestCase
{
    public function test_refuses_pricing_without_rates(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['object' => 'pricing', 'tokens' => []])]);

        $pricing = new CurrentPricing(
            new PixelforgeClient($http, 'https://pixelforge.test/v1', ''),
            new Repository(new ArrayStore),
            new Markup(25),
            cacheSeconds: 600,
        );

        $this->expectException(UnexpectedValueException::class);

        $pricing->calculator();
    }
}
