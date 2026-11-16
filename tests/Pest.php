<?php

use App\Models\Generation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => Http::preventStrayRequests())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Fake GET /v1/pricing with the rates and token rules from the Pixelforge docs.
 */
function fakePixelforgePricing(): void
{
    Http::fake([
        'pixelforge.promptedit.dev/v1/pricing' => Http::response(
            json_decode(file_get_contents(__DIR__.'/Fixtures/pixelforge-pricing.json'), true),
        ),
    ]);
}

/**
 * The generation object Pixelforge would return for one of ours, using the usage from the docs' example.
 *
 * That usage costs Pixelforge $0.305510000, which is $0.381887500 to the user after the 25% markup.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function pixelforgeSnapshot(Generation $generation, string $status, array $overrides = []): array
{
    $succeeded = $status === 'succeeded';
    $failed = $status === 'failed';

    return [
        'id' => $generation->provider_id ?? 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
        'object' => 'generation',
        'status' => $status,
        'prompt' => $generation->prompt,
        'size' => $generation->size,
        'quality' => $generation->quality,
        'reference_images' => $generation->reference_images,
        'metadata' => ['generation_id' => $generation->id],
        'image_url' => $succeeded ? 'https://pixelforge.promptedit.dev/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png?signature=abc' : null,
        'image_url_expires_at' => $succeeded ? '2026-11-05T14:03:29Z' : null,
        'usage' => $succeeded ? [
            'input_text_tokens' => 12,
            'input_image_tokens' => 765,
            'output_tokens' => 6450,
            'reasoning_tokens' => 3184,
            'total_tokens' => 10411,
        ] : null,
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'error' => $failed ? ['code' => 'generation_failed', 'message' => 'The model could not produce an image for this prompt.'] : null,
        'created_at' => '2026-11-04T14:03:11Z',
        'started_at' => $status === 'queued' ? null : '2026-11-04T14:03:13Z',
        'completed_at' => $succeeded || $failed ? '2026-11-04T14:03:29Z' : null,
        ...$overrides,
    ];
}

/**
 * Deliver a webhook event the way Pixelforge does: a raw JSON body signed with the key's secret.
 *
 * @param  array<string, mixed>  $snapshot
 */
function deliverPixelforgeEvent(string $type, array $snapshot, string $eventId = 'evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A', ?int $signedAt = null, string $secret = 'whsec_test'): TestResponse
{
    $body = json_encode([
        'id' => $eventId,
        'object' => 'event',
        'type' => $type,
        'created_at' => '2026-11-04T14:03:29Z',
        'data' => ['object' => $snapshot],
    ]);

    $signedAt ??= now()->getTimestamp();
    $signature = hash_hmac('sha256', "{$signedAt}.{$body}", $secret);

    return test()->call('POST', '/webhooks/pixelforge', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_PIXELFORGE_SIGNATURE' => "t={$signedAt},v1={$signature}",
    ], content: $body);
}
