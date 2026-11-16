<?php

use App\Actions\Webhooks\HandlePixelforgeEvent;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * These tests run a generation through the whole app: the form request, the queued job, the signed webhook
 * endpoint, the polling command and the pages. Only Pixelforge's HTTP API is faked.
 *
 * They use the generation from the Pixelforge docs: 45 characters, high quality, 1536x1024, one reference image.
 * It is estimated at $0.40245 (at most $0.45715) and its real usage costs the user $0.3818875.
 */

beforeEach(function () {
    $this->travelTo('2026-11-04 14:03:00');
    fakePixelforgePricing();
});

/**
 * @return array<string, mixed>
 */
function lighthouse(): array
{
    return [
        'prompt' => 'A lighthouse on a cliff at dawn, oil painting',
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/style.png'],
    ];
}

function userWhoBought(int $dollars): User
{
    $user = User::factory()->create();

    test()->actingAs($user)->post(route('credits.store'), ['amount' => $dollars])->assertSessionHasNoErrors();

    return $user;
}

/**
 * Make Pixelforge accept every create request, optionally doing something just before it answers.
 */
function pixelforgeAccepts(?Closure $beforeResponding = null): void
{
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => function (Request $request) use ($beforeResponding) {
            $generation = Generation::query()->findOrFail($request['metadata']['generation_id']);

            if ($beforeResponding !== null) {
                $beforeResponding($generation);
            }

            return Http::response(pixelforgeSnapshot($generation, 'queued'), 202);
        },
    ]);
}

test('a generation is bought, submitted, completed by webhooks and shown at its real cost', function () {
    pixelforgeAccepts();
    $user = userWhoBought(10);

    $this->actingAs($user)->post(route('generations.store'), lighthouse())->assertRedirect(route('dashboard'));
    $generation = $user->generations()->sole();
    deliverPixelforgeEvent('generation.processing', pixelforgeSnapshot($generation->refresh(), 'processing'), 'evt_processing')->assertNoContent();
    deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($generation, 'succeeded'), 'evt_succeeded')->assertNoContent();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('generations.data.0.status', 'succeeded')
        ->where('generations.data.0.estimated_price_nanos', 402_450_000)
        ->where('generations.data.0.max_price_nanos', 457_150_000)
        ->where('generations.data.0.price_nanos', 381_887_500)
        ->where('generations.data.0.usage.reasoning_tokens', 3184)
        ->where('generations.data.0.image_url', 'https://pixelforge.promptedit.dev/images/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K.png?signature=abc')
        ->where('credits', ['balance' => 9_618_112_500, 'reserved' => 0, 'available' => 9_618_112_500])
    );
});

test('the admin report matches what Pixelforge bills for a completed generation', function () {
    pixelforgeAccepts();
    $user = userWhoBought(10);
    $this->actingAs($user)->post(route('generations.store'), lighthouse());
    deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($user->generations()->sole(), 'succeeded'));
    Http::fake([
        'pixelforge.promptedit.dev/v1/usage*' => Http::response(['object' => 'usage', 'data' => [
            ['date' => '2026-11-04', 'generations' => 1, 'cost_usd' => '0.305510000'],
        ]]),
    ]);

    $response = $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.report'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('days.0.date', '2026-11-04')
        ->where('days.0.charged_nanos', 381_887_500)
        ->where('days.0.provider_cost_nanos', 305_510_000)
        ->where('days.0.profit_nanos', 76_377_500)
        ->where('days.0.pixelforge', ['generations' => 1, 'cost_nanos' => 305_510_000])
        ->where('days.0.users.0.user.id', $user->id)
    );
});

test('a generation is charged once when its webhooks arrive early, twice and out of order', function () {
    pixelforgeAccepts(beforeResponding: fn (Generation $generation) => app(HandlePixelforgeEvent::class)->handle([
        'id' => 'evt_succeeded',
        'type' => 'generation.succeeded',
        'data' => ['object' => pixelforgeSnapshot($generation, 'succeeded')],
    ]));
    $user = userWhoBought(10);

    $this->actingAs($user)->post(route('generations.store'), lighthouse());
    $generation = $user->generations()->sole();
    deliverPixelforgeEvent('generation.succeeded', pixelforgeSnapshot($generation, 'succeeded'), 'evt_succeeded')->assertNoContent();
    deliverPixelforgeEvent('generation.processing', pixelforgeSnapshot($generation, 'processing'), 'evt_processing')->assertNoContent();
    deliverPixelforgeEvent('generation.processing', pixelforgeSnapshot($generation, 'processing'), 'evt_processing')->assertNoContent();

    $generation->refresh();
    expect($generation->status)->toBe(GenerationStatus::Succeeded)
        ->and($generation->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K')
        ->and($generation->price_nanos)->toBe(381_887_500)
        ->and($user->credits()['balance'])->toBe(9_618_112_500)
        ->and(WebhookEvent::query()->pluck('event_id')->sort()->values()->all())->toBe(['evt_processing', 'evt_succeeded']);
});

test('a generation that fails after being accepted is free and releases its hold', function () {
    pixelforgeAccepts();
    $user = userWhoBought(10);

    $this->actingAs($user)->post(route('generations.store'), lighthouse());
    expect($user->credits())->toBe(['balance' => 10_000_000_000, 'reserved' => 457_150_000, 'available' => 9_542_850_000]);
    deliverPixelforgeEvent('generation.failed', pixelforgeSnapshot($user->generations()->sole(), 'failed'))->assertNoContent();

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('generations.data.0.status', 'failed')
        ->where('generations.data.0.price_nanos', 0)
        ->where('generations.data.0.error_message', 'The model could not produce an image for this prompt.')
        ->where('credits', ['balance' => 10_000_000_000, 'reserved' => 0, 'available' => 10_000_000_000])
    );
});

test('a prompt Pixelforge rejects is shown as failed with the reason and no charge', function () {
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => Http::response(
            ['error' => ['type' => 'invalid_request_error', 'code' => 'content_policy_violation', 'message' => 'The prompt was rejected.']],
            400,
        ),
    ]);
    $user = userWhoBought(10);

    $this->actingAs($user)->post(route('generations.store'), lighthouse())->assertRedirect(route('dashboard'));

    $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('generations.data.0.status', 'failed')
        ->where('generations.data.0.error_message', 'Your prompt was rejected by the content policy. You were not charged.')
        ->where('credits.available', 10_000_000_000)
    );
});

test('a busy Pixelforge is retried by the queue worker with the same idempotency key', function () {
    config(['queue.default' => 'database']);
    $idempotencyKeys = [];
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations' => function (Request $request) use (&$idempotencyKeys) {
            $idempotencyKeys[] = $request->header('Idempotency-Key')[0];

            return count($idempotencyKeys) === 1
                ? Http::response(['error' => ['type' => 'api_error', 'code' => 'overloaded', 'message' => 'Overloaded.']], 503, ['Retry-After' => 2])
                : Http::response(pixelforgeSnapshot(Generation::query()->findOrFail($request['metadata']['generation_id']), 'queued'), 202);
        },
    ]);
    $user = userWhoBought(10);
    $this->actingAs($user)->post(route('generations.store'), lighthouse());
    $generation = $user->generations()->sole();

    $this->artisan('queue:work', ['--once' => true]);
    $this->artisan('queue:work', ['--once' => true]);
    expect($idempotencyKeys)->toHaveCount(1);
    $this->travel(2)->seconds();
    $this->artisan('queue:work', ['--once' => true]);

    expect($idempotencyKeys)->toBe([$generation->id, $generation->id])
        ->and($generation->refresh()->provider_id)->toBe('gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K');
});

test('a generation whose webhooks never arrive is completed by the polling command', function () {
    pixelforgeAccepts();
    $user = userWhoBought(10);
    $this->actingAs($user)->post(route('generations.store'), lighthouse());
    $generation = $user->generations()->sole();
    Http::fake([
        'pixelforge.promptedit.dev/v1/generations/gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K' => Http::response(pixelforgeSnapshot($generation, 'succeeded')),
    ]);

    $this->artisan('generations:reconcile')->assertSuccessful();
    expect($generation->refresh()->status)->toBe(GenerationStatus::Queued);
    $this->travel(61)->seconds();
    $this->artisan('generations:reconcile')->assertSuccessful();

    expect($generation->refresh()->status)->toBe(GenerationStatus::Succeeded)
        ->and($user->credits())->toBe(['balance' => 9_618_112_500, 'reserved' => 0, 'available' => 9_618_112_500]);
});
