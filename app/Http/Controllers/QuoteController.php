<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Pricing\CurrentPricing;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Prices a generation before it is submitted.
 *
 * The browser asks here instead of doing the maths itself, so the estimate
 * and the final charge always come from the same PriceCalculator.
 */
class QuoteController extends Controller
{
    public function __invoke(QuoteRequest $request, CurrentPricing $pricing): JsonResponse
    {
        try {
            $calculator = $pricing->calculator();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Could not load Pixelforge pricing.', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Prices are unavailable right now. Please try again in a moment.'], 503);
        }

        $quote = $calculator->quote(
            $request->prompt(),
            $request->string('size')->toString(),
            $request->string('quality')->toString(),
            $request->referenceImageCount(),
        );

        return response()->json([
            'min_charge' => $quote->minCharge->toDecimalString(),
            'max_charge' => $quote->maxCharge->toDecimalString(),
        ]);
    }
}
