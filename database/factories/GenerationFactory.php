<?php

namespace Database\Factories;

use App\Enums\GenerationStatus;
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
     * Define the model's default state: queued locally, not yet sent to Pixelforge.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'prompt' => fake()->sentence(),
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images' => [],
            'estimated_price_nanos' => 72_250_000,
            'max_price_nanos' => 86_325_000,
        ];
    }

    /**
     * Indicate that Pixelforge has accepted the generation.
     */
    public function submitted(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider_id' => 'gen_'.Str::ulid(),
            'rates' => [
                'input_text_tokens' => '5.00',
                'input_image_tokens' => '10.00',
                'output_tokens' => '40.00',
                'reasoning_tokens' => '12.50',
            ],
        ]);
    }

    /**
     * Indicate that the generation succeeded and was charged.
     */
    public function succeeded(): static
    {
        return $this->submitted()->state(fn (array $attributes) => [
            'status' => GenerationStatus::Succeeded,
            'input_text_tokens' => 10,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 800,
            'provider_cost_nanos' => 54_050_000,
            'price_nanos' => 67_562_500,
            'image_url' => 'https://pixelforge.promptedit.dev/images/example.png',
            'image_url_expires_at' => now()->addDay(),
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    /**
     * Indicate that the generation failed, which costs nothing.
     */
    public function failed(): static
    {
        return $this->submitted()->state(fn (array $attributes) => [
            'status' => GenerationStatus::Failed,
            'error_code' => 'generation_failed',
            'error_message' => 'The model could not produce an image for this prompt.',
            'provider_cost_nanos' => 0,
            'price_nanos' => 0,
            'completed_at' => now(),
        ]);
    }
}
