<?php

namespace App\Pixelforge;

/**
 * The image sizes Pixelforge accepts.
 */
enum ImageSize: string
{
    case Square = '1024x1024';
    case Landscape = '1536x1024';
    case Portrait = '1024x1536';

    public const DEFAULT = self::Square;

    public function label(): string
    {
        return match ($this) {
            self::Square => 'Square · 1024 × 1024',
            self::Landscape => 'Landscape · 1536 × 1024',
            self::Portrait => 'Portrait · 1024 × 1536',
        };
    }
}
