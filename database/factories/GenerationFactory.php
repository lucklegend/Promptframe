<?php

namespace Database\Factories;

use App\Generations\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Generation>
 */
class GenerationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Submitting,
            'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'estimated_min_charge' => 58_162_500,
            'estimated_max_charge' => 86_312_500,
        ];
    }

    public function status(GenerationStatus $status): static
    {
        return $this->state(['status' => $status]);
    }
}
