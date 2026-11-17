<?php

namespace App\Http\Controllers;

use App\Generations\GenerationStatus;
use App\Http\Requests\GenerationRules;
use App\Http\Requests\StoreGenerationRequest;
use App\Jobs\SubmitGeneration;
use App\Pixelforge\ImageQuality;
use App\Pixelforge\ImageSize;
use App\Pricing\CurrentPricing;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class GenerationController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Generate', [
            'sizes' => array_map(fn (ImageSize $size) => ['value' => $size->value, 'label' => $size->label()], ImageSize::cases()),
            'qualities' => array_map(fn (ImageQuality $quality) => ['value' => $quality->value, 'label' => $quality->label()], ImageQuality::cases()),
            'defaults' => ['size' => ImageSize::DEFAULT->value, 'quality' => ImageQuality::DEFAULT->value],
            'limits' => [
                'promptCharacters' => GenerationRules::MAX_PROMPT_CHARACTERS,
                'referenceImages' => GenerationRules::MAX_REFERENCE_IMAGES,
            ],
        ]);
    }

    /**
     * Saves the generation, then hands it to the queue to send to Pixelforge.
     *
     * The row is written first so that whatever happens next (a retry, a
     * webhook that beats the response back) always has a record to update.
     */
    public function store(StoreGenerationRequest $request, CurrentPricing $pricing, Dispatcher $bus): JsonResponse
    {
        try {
            $calculator = $pricing->calculator();
        } catch (ConnectionException|RequestException $exception) {
            Log::warning('Could not load Pixelforge pricing.', ['error' => $exception->getMessage()]);

            return response()->json(['message' => 'Prices are unavailable right now. Please try again in a moment.'], 503);
        }

        $referenceImages = $request->referenceImages();
        $quote = $calculator->quote(
            $request->prompt(),
            $request->string('size')->toString(),
            $request->string('quality')->toString(),
            count($referenceImages),
        );

        $generation = $request->user()->generations()->create([
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Submitting,
            'prompt' => $request->prompt(),
            'size' => $request->string('size')->toString(),
            'quality' => $request->string('quality')->toString(),
            'reference_images' => $referenceImages,
            'estimated_min_charge' => $quote->minCharge->amount,
            'estimated_max_charge' => $quote->maxCharge->amount,
        ]);

        $bus->dispatch(new SubmitGeneration($generation));

        return response()->json([
            'id' => $generation->getKey(),
            'status' => $generation->status->value,
        ], 202);
    }
}
