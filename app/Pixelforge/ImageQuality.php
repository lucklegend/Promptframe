<?php

namespace App\Pixelforge;

/**
 * The quality levels Pixelforge accepts. Higher quality uses more output
 * and reasoning tokens, so it costs more.
 */
enum ImageQuality: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public const DEFAULT = self::Medium;

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
