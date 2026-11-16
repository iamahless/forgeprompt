<?php

use App\Enums\GenerationStatus;
use App\Jobs\SubmitGeneration;
use App\Models\Generation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

function submit(Generation $generation, int $attempt = 1): SubmitGeneration
{
    $job = (new SubmitGeneration($generation))->withFakeQueueInteractions();
    $job->job->attempts = $attempt;

    app()->call([$job, 'handle']);

    return $job;
}

test('a generation is submitted with its id as the idempotency key and in the metadata', function () {
    $generation = Generation::factory()->create([
        'prompt' => 'A lighthouse at dawn',
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/style.png'],
    ]);
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(pixelforgeSnapshot($generation, 'queued'), 202),
    ]);

    submit($generation);

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Idempotency-Key', $generation->id)
        && $request->hasHeader('Authorization', 'Bearer pf_test_key')
        && $request->data() === [
            'prompt' => 'A lighthouse at dawn',
            'size' => '1536x1024',
            'quality' => 'high',
            'reference_images' => ['https://example.com/style.png'],
            'metadata' => ['generation_id' => $generation->id],
        ]);
    $generation->refresh();
    expect($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->status)->toBe(GenerationStatus::Queued)
        ->and($generation->rates['output_tokens'])->toBe('40.00');
});

test('a generation is retried after the delay Pixelforge asks for when it is busy', function (int $status, string $code, int $retryAfter) {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(
            ['error' => ['type' => 'rate_limit_error', 'code' => $code, 'message' => 'Busy.']],
            $status,
            ['Retry-After' => $retryAfter],
        ),
    ]);

    $job = submit($generation);

    $job->assertReleased(delay: $retryAfter);
    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
})->with([
    'overloaded' => [503, 'overloaded', 2],
    'too many concurrent generations' => [429, 'concurrency_limit_exceeded', 5],
    'too many requests this minute' => [429, 'rate_limit_exceeded', 37],
]);

test('a generation is retried when Pixelforge cannot be reached', function () {
    $generation = Generation::factory()->create();
    Http::fake(['pixelforge.promptedit.dev/v1/generations' => Http::failedConnection()]);

    $job = submit($generation);

    $job->assertReleased(delay: 2);
});

test('a generation fails without a charge once Pixelforge has been busy for every retry', function () {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(
            ['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Pixelforge is temporarily overloaded.']],
            503,
            ['Retry-After' => 2],
        ),
    ]);

    $job = submit($generation, attempt: 8);

    $job->assertNotReleased();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->price_nanos)->toBe(0)
        ->and($generation->error_message)->toBe('Pixelforge is too busy right now. You were not charged. Please try again in a few minutes.');
});

test('a prompt rejected by the content policy fails without a charge or a retry', function () {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(
            ['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'The prompt was rejected.']],
            400,
        ),
    ]);

    $job = submit($generation);

    $job->assertNotReleased();
    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->price_nanos)->toBe(0)
        ->and($generation->error_code)->toBe('content_policy_violation')
        ->and($generation->error_message)->toBe('Your prompt was rejected by the content policy. You were not charged.');
});

test('a used-up daily quota fails the generation instead of retrying all day', function () {
    $generation = Generation::factory()->create();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(
            ['error' => ['type' => 'rate_limit_error', 'code' => 'daily_limit_exceeded', 'message' => 'Daily quota used up.']],
            429,
        ),
    ]);

    $job = submit($generation);

    $job->assertNotReleased();
    expect($generation->refresh()->status)->toBe(GenerationStatus::Failed);
});

test('a generation Pixelforge already accepted is not submitted again', function () {
    $generation = Generation::factory()->submitted()->create();
    Http::fake();

    submit($generation);

    Http::assertNothingSent();
});

test('a job that keeps crashing fails the generation without a charge', function () {
    $generation = Generation::factory()->create();

    (new SubmitGeneration($generation))->failed(new RuntimeException('Too many attempts.'));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Failed)
        ->and($generation->price_nanos)->toBe(0)
        ->and($generation->error_message)->toBe('Pixelforge is too busy right now. You were not charged. Please try again in a few minutes.');
});

test('a crashing job does not overwrite a result that already arrived', function () {
    $generation = Generation::factory()->succeeded()->create();

    (new SubmitGeneration($generation))->failed(new RuntimeException('Too many attempts.'));

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->price_nanos)->toBe(67_562_500);
});
