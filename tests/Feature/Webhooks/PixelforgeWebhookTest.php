<?php

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\WebhookEvent;

test('a succeeded event records the usage and charges the cost plus markup', function () {
    $generation = Generation::factory()->submitted()->create();

    $response = deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($generation, 'succeeded'));

    $response->assertNoContent();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_cost_nanos)->toBe(305_510_000)
        ->and($generation->price_nanos)->toBe(381_887_500)
        ->and($generation->only(['input_text_tokens', 'input_image_tokens', 'output_tokens', 'reasoning_tokens']))->toBe([
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
        ])
        ->and($generation->image_url)->toBe('https://pixelforge.promptedit.dev/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png?signature=abc')
        ->and($generation->completed_at->toIso8601ZuluString())->toBe('2026-11-04T14:03:29Z');
});

test('an event that arrives before the create request returns is matched by our own id', function () {
    $generation = Generation::factory()->create();

    $response = deliverPixelforgeEvent('generation.processing', pixelforgeSnapshot($generation, 'processing'));

    $response->assertNoContent();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Processing)
        ->and($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K');
});

test('an event delivered twice is recorded and charged once', function () {
    $generation = Generation::factory()->submitted()->create();
    $snapshot = pixelforgeSnapshot($generation, 'succeeded');

    deliverPixelforgeEvent('generation.succeeded', $snapshot, 'evt_duplicate')->assertNoContent();
    deliverPixelforgeEvent('generation.succeeded', $snapshot, 'evt_duplicate')->assertNoContent();

    expect(WebhookEvent::query()->where('event_id', 'evt_duplicate')->count())->toBe(1)
        ->and($generation->user->credits()['balance'])->toBe(-381_887_500);
});

test('a processing event that arrives after the generation finished does not move it back', function () {
    $generation = Generation::factory()->submitted()->create();
    deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($generation, 'succeeded'), 'evt_succeeded');

    $response = deliverPixelforgeEvent('generation.processing', pixelforgeSnapshot($generation, 'processing'), 'evt_processing');

    $response->assertNoContent();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->price_nanos)->toBe(381_887_500);
});

test('a failed event costs nothing and keeps the reason', function () {
    $generation = Generation::factory()->submitted()->create();

    $response = deliverPixelforgeEvent('generation.failed', pixelforgeSnapshot($generation, 'failed'));

    $response->assertNoContent();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->price_nanos)->toBe(0)
        ->and($generation->provider_cost_nanos)->toBe(0)
        ->and($generation->error_message)->toBe('The model could not produce an image for this prompt.');
});

test('a finished generation is never charged by a later event', function () {
    $generation = Generation::factory()->failed()->create();

    $response = deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($generation, 'succeeded'));

    $response->assertNoContent();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->price_nanos)->toBe(0);
});

test('an event with a signature from the wrong secret is rejected with 400', function () {
    $generation = Generation::factory()->submitted()->create();

    $response = deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($generation, 'succeeded'), secret: 'whsec_wrong');

    $response->assertBadRequest();
    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued)
        ->and(WebhookEvent::query()->count())->toBe(0);
});

test('an event without a signature is rejected with 400', function () {
    $response = $this->postJson('/webhooks/pixelforge', ['id' => 'evt_unsigned', 'type' => 'ping']);

    $response->assertBadRequest();
    expect(WebhookEvent::query()->count())->toBe(0);
});

test('an event signed more than five minutes ago is rejected with 400', function () {
    $this->freezeTime();
    $generation = Generation::factory()->submitted()->create();

    $response = deliverPixelforgeEvent(
        'generation.succeeded',
        pixelforgeSnapshot($generation, 'succeeded'),
        signedAt: now()->subSeconds(301)->getTimestamp(),
    );

    $response->assertBadRequest();
    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
});

test('an event for a generation we do not know is acknowledged and ignored', function () {
    $stranger = Generation::factory()->submitted()->make(['id' => '019a0000-0000-7000-8000-000000000000']);

    $response = deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($stranger, 'succeeded'));

    $response->assertNoContent();
    expect(Generation::query()->count())->toBe(0);
});

test('a signed body that is not an event is rejected with 422', function () {
    $body = '{"hello":"world"}';
    $signature = hash_hmac('sha256', now()->getTimestamp().'.'.$body, 'whsec_test');

    $response = $this->call('POST', '/webhooks/pixelforge', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => 't='.now()->getTimestamp().',v1='.$signature,
    ], content: $body);

    $response->assertUnprocessable();
    expect(WebhookEvent::query()->count())->toBe(0);
});

test('a ping from the dashboard is acknowledged', function () {
    $response = deliverPixelforgeEvent('ping', []);

    $response->assertNoContent();
});
