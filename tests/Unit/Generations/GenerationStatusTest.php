<?php

namespace Tests\Unit\Generations;

use App\Generations\GenerationStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GenerationStatusTest extends TestCase
{
    /**
     * @return array<string, array{GenerationStatus, GenerationStatus, bool}>
     */
    public static function moves(): array
    {
        return [
            'submitting to queued' => [GenerationStatus::Submitting, GenerationStatus::Queued, true],
            'queued to processing' => [GenerationStatus::Queued, GenerationStatus::Processing, true],
            'queued straight to succeeded' => [GenerationStatus::Queued, GenerationStatus::Succeeded, true],
            'processing to failed' => [GenerationStatus::Processing, GenerationStatus::Failed, true],
            'submitting straight to failed' => [GenerationStatus::Submitting, GenerationStatus::Failed, true],
            'a late processing never undoes succeeded' => [GenerationStatus::Succeeded, GenerationStatus::Processing, false],
            'processing back to queued' => [GenerationStatus::Processing, GenerationStatus::Queued, false],
            'succeeded to failed' => [GenerationStatus::Succeeded, GenerationStatus::Failed, false],
            'failed to succeeded' => [GenerationStatus::Failed, GenerationStatus::Succeeded, false],
            'queued to queued' => [GenerationStatus::Queued, GenerationStatus::Queued, false],
        ];
    }

    #[DataProvider('moves')]
    public function test_status_only_moves_forward(GenerationStatus $from, GenerationStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canMoveTo($to));
    }
}
