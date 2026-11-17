<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuoteRequest;
use App\Pixelforge\ImageQuality;
use App\Pixelforge\ImageSize;
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
                'promptCharacters' => QuoteRequest::MAX_PROMPT_CHARACTERS,
                'referenceImages' => QuoteRequest::MAX_REFERENCE_IMAGES,
            ],
        ]);
    }
}
