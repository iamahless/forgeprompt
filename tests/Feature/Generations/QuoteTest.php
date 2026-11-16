<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;

test('guests cannot request a quote', function () {
    $response = $this->postJson(route('generations.quote'), ['size' => '1024x1024', 'quality' => 'low', 'reference_image_count' => 0]);

    $response->assertUnauthorized();
});

test('a quote gives the expected and the highest possible price with the markup', function () {
    fakePixelforgePricing();

    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_image_count' => 2,
    ]);

    // Pixelforge: 100 to 115 text tokens, 1,530 image, 6,450 output and 4,500 (midpoint) to 8,000 reasoning.
    $response->assertOk()->assertExactJson([
        'estimated_price_nanos' => 412_562_500,
        'max_price_nanos' => 467_343_750,
    ]);
});

test('a prompt is counted in characters, not bytes', function () {
    fakePixelforgePricing();

    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), [
        'prompt' => 'ééééé',
        'size' => '1024x1024',
        'quality' => 'low',
        'reference_image_count' => 0,
    ]);

    // Five characters are 2 text tokens (3 at most): (2 × 5,000 + 280 × 40,000 + 250 × 12,500) × 1.25.
    $response->assertOk()->assertExactJson([
        'estimated_price_nanos' => 17_918_750,
        'max_price_nanos' => 21_831_250,
    ]);
});

test('an empty form can still be quoted', function () {
    fakePixelforgePricing();

    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), [
        'prompt' => '',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_image_count' => 0,
    ]);

    // 1,100 output tokens and 1,100 (midpoint) to 2,000 reasoning tokens.
    $response->assertOk()->assertExactJson([
        'estimated_price_nanos' => 72_187_500,
        'max_price_nanos' => 86_250_000,
    ]);
});

test('a quote returns 503 when Pixelforge pricing is unavailable', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::failedConnection()]);

    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), [
        'prompt' => 'A lighthouse',
        'size' => '1024x1024',
        'quality' => 'medium',
        'reference_image_count' => 0,
    ]);

    $response->assertServiceUnavailable();
});

test('a quote for an unknown size returns 422', function () {
    $response = $this->actingAs(User::factory()->create())->postJson(route('generations.quote'), [
        'size' => '512x512',
        'quality' => 'medium',
        'reference_image_count' => 0,
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['size' => 'The selected size is invalid.']);
});
