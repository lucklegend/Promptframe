<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GeneratePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_shows_the_options_pixelforge_accepts(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/generate')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Generate')
                ->where('sizes', fn ($sizes) => collect($sizes)->pluck('value')->all() === ['1024x1024', '1536x1024', '1024x1536'])
                ->where('qualities', fn ($qualities) => collect($qualities)->pluck('value')->all() === ['low', 'medium', 'high'])
                ->where('defaults', ['size' => '1024x1024', 'quality' => 'medium'])
                ->where('limits', ['promptCharacters' => 4000, 'referenceImages' => 4]));
    }

    public function test_guests_are_sent_to_log_in(): void
    {
        $this->get('/generate')->assertRedirect('/login');
    }
}
