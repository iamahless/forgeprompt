<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Illuminate\Support\Facades\Http;

test('a generation whose webhook never arrived is fetched and charged', function () {
    $generation = Generation::factory()->submitted()->create(['updated_at' => now()->subMinutes(2)]);
    Http::fake([
        "pixelforge.promptedit.dev/v1/generations/{$generation->provider_id}" => Http::response(pixelforgeSnapshot($generation, 'succeeded')),
    ]);

    $this->artisan('generations:reconcile')->assertSuccessful();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->price_nanos)->toBe(381_887_500);
});

test('generations that are recent, finished or not yet submitted are left alone', function () {
    Generation::factory()->submitted()->create();
    Generation::factory()->succeeded()->create(['updated_at' => now()->subMinutes(2)]);
    Generation::factory()->create(['updated_at' => now()->subMinutes(2)]);
    Http::fake();

    $this->artisan('generations:reconcile')->assertSuccessful();

    Http::assertNothingSent();
});

test('a generation Pixelforge cannot return right now stays in flight for the next run', function () {
    $generation = Generation::factory()->submitted()->create(['updated_at' => now()->subMinutes(2)]);
    Http::fake([
        "pixelforge.promptedit.dev/v1/generations/{$generation->provider_id}" => Http::response(
            ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Pixelforge is temporarily overloaded.']],
            503,
        ),
    ]);

    $this->artisan('generations:reconcile')->assertSuccessful();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
});
