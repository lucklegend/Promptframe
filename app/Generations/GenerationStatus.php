<?php

namespace App\Generations;

/**
 * Where a generation is in its life.
 *
 * Submitting is ours: the row exists but Pixelforge hasn't accepted it yet.
 * The other four are Pixelforge's statuses, with the same names.
 *
 * Status only ever moves forward. Webhooks can arrive late or out of order,
 * so a "processing" event that turns up after "succeeded" must not undo it.
 */
enum GenerationStatus: string
{
    case Submitting = 'submitting';
    case Queued = 'queued';
    case Processing = 'processing';
    case Succeeded = 'succeeded';
    case Failed = 'failed';

    public function isFinal(): bool
    {
        return $this === self::Succeeded || $this === self::Failed;
    }

    public function canMoveTo(self $next): bool
    {
        return ! $this->isFinal() && $next->step() > $this->step();
    }

    /**
     * How far along the lifecycle this status is. Succeeded and failed share
     * the last step: both are final, and neither can replace the other.
     */
    private function step(): int
    {
        return match ($this) {
            self::Submitting => 0,
            self::Queued => 1,
            self::Processing => 2,
            self::Succeeded, self::Failed => 3,
        };
    }
}
