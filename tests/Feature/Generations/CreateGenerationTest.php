<?php

use App\Jobs\SubmitGeneration;
use App\Models\CreditPurchase;
use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

/**
 * The docs' worked example: at most $0.373875 from Pixelforge, so at most $0.46734375 to the user.
 *
 * @return array<string, mixed>
 */
function worstCaseExample(): array
{
    return [
        'prompt' => str_repeat('a', 400),
        'size' => '1536x1024',
        'quality' => 'high',
        'reference_images' => ['https://example.com/one.png', 'https://example.com/two.png'],
    ];
}

function userWithCredits(int $nanos): User
{
    $user = User::factory()->create();
    $user->creditPurchases()->create(['amount_nanos' => $nanos]);

    return $user;
}

test('guests cannot generate images', function () {
    $response = $this->post(route('generations.store'), worstCaseExample());

    $response->assertRedirect(route('login'));
});

test('a generation is quoted, saved as queued and handed to the queue', function () {
    fakePixelforgePricing();
    Queue::fake([SubmitGeneration::class]);
    $user = userWithCredits(1_000_000_000);

    $response = $this->actingAs($user)->post(route('generations.store'), worstCaseExample());

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    $generation = $user->generations()->sole();
    expect($generation->status->value)->toBe('queued')
        ->and($generation->estimated_price_nanos)->toBe(412_562_500)
        ->and($generation->max_price_nanos)->toBe(467_343_750)
        ->and($generation->reference_images)->toBe(['https://example.com/one.png', 'https://example.com/two.png']);
    Queue::assertPushed(SubmitGeneration::class, fn (SubmitGeneration $job): bool => $job->generation->is($generation));
});

test('a generation is blocked when the user cannot afford the most it could cost', function () {
    fakePixelforgePricing();
    Queue::fake([SubmitGeneration::class]);
    $user = userWithCredits(467_343_749);

    $response = $this->actingAs($user)->post(route('generations.store'), worstCaseExample());

    $response->assertSessionHasErrors([
        'credits' => 'This could cost up to $0.46734375, but you have $0.467343749 available. Buy credits to continue.',
    ]);
    expect($user->generations()->count())->toBe(0);
    Queue::assertNotPushed(SubmitGeneration::class);
});

test('credits reserved by generations still in flight cannot be spent twice', function () {
    fakePixelforgePricing();
    Queue::fake([SubmitGeneration::class]);
    $user = userWithCredits(500_000_000);
    Generation::factory()->for($user)->create(['max_price_nanos' => 100_000_000]);

    $response = $this->actingAs($user)->post(route('generations.store'), worstCaseExample());

    $response->assertSessionHasErrors('credits');
    Queue::assertNotPushed(SubmitGeneration::class);
});

test('invalid input is rejected with a message', function (array $input, string $field, string $message) {
    Queue::fake([SubmitGeneration::class]);
    $user = userWithCredits(1_000_000_000);

    $response = $this->actingAs($user)->post(route('generations.store'), [...worstCaseExample(), ...$input]);

    $response->assertSessionHasErrors([$field => $message]);
    expect($user->generations()->count())->toBe(0);
})->with([
    'no prompt' => [['prompt' => ''], 'prompt', 'The prompt field is required.'],
    'a prompt over 4,000 characters' => [['prompt' => str_repeat('a', 4001)], 'prompt', 'The prompt field must not be greater than 4000 characters.'],
    'an unknown size' => [['size' => '512x512'], 'size', 'The selected size is invalid.'],
    'an unknown quality' => [['quality' => 'ultra'], 'quality', 'The selected quality is invalid.'],
    'five reference images' => [
        ['reference_images' => array_fill(0, 5, 'https://example.com/style.png')],
        'reference_images',
        'The reference images field must not have more than 4 items.',
    ],
    'a reference image that is not a web address' => [
        ['reference_images' => ['ftp://example.com/style.png']],
        'reference_images.0',
        'The reference image URL field must be a valid URL.',
    ],
]);

test('blank reference image rows are ignored', function () {
    fakePixelforgePricing();
    Queue::fake([SubmitGeneration::class]);
    $user = userWithCredits(1_000_000_000);

    $response = $this->actingAs($user)->post(route('generations.store'), [
        ...worstCaseExample(),
        'reference_images' => ['', 'https://example.com/one.png', null],
    ]);

    $response->assertSessionHasNoErrors();
    expect($user->generations()->sole()->reference_images)->toBe(['https://example.com/one.png']);
});

test('an eleventh generation within a minute is refused with a message', function () {
    fakePixelforgePricing();
    Queue::fake([SubmitGeneration::class]);
    $user = userWithCredits(10_000_000_000);
    foreach (range(1, 10) as $attempt) {
        $this->actingAs($user)->post(route('generations.store'), worstCaseExample())->assertSessionHasNoErrors();
    }

    $response = $this->actingAs($user)->from(route('dashboard'))->post(route('generations.store'), worstCaseExample());

    $response->assertRedirect(route('dashboard'))->assertSessionHasErrors([
        'throttle' => 'You are generating images too quickly. Please wait a minute and try again.',
    ]);
    expect($user->generations()->count())->toBe(10);
});

test('nothing is created when Pixelforge pricing is unavailable', function () {
    Http::fake(['pixelforge.promptedit.dev/v1/pricing' => Http::response(status: 503)]);
    Queue::fake([SubmitGeneration::class]);
    $user = userWithCredits(1_000_000_000);

    $response = $this->actingAs($user)->post(route('generations.store'), worstCaseExample());

    $response->assertSessionHasErrors('pricing');
    expect(Generation::query()->count())->toBe(0)
        ->and(CreditPurchase::query()->sum('amount_nanos'))->toBe(1_000_000_000);
    Queue::assertNotPushed(SubmitGeneration::class);
});
