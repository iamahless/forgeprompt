<?php

use App\Models\Generation;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

function fakePixelforgeUsage(array $days): void
{
    Http::fake([
        'pixelforge.promptedit.dev/v1/usage*' => Http::response(['object' => 'usage', 'data' => $days]),
    ]);
}

test('guests cannot see the admin report', function () {
    $response = $this->get(route('admin.report'));

    $response->assertRedirect(route('login'));
});

test('users who are not admins get 403 for the admin report', function () {
    $response = $this->actingAs(User::factory()->create())->get(route('admin.report'));

    $response->assertForbidden();
});

test('the report totals charges, provider costs and profit per day and per user', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    $ada = User::factory()->create(['name' => 'Ada']);
    $grace = User::factory()->create(['name' => 'Grace']);
    Generation::factory()->for($ada)->succeeded()->create([
        'provider_cost_nanos' => 305_510_000, 'price_nanos' => 381_887_500, 'completed_at' => '2026-11-15 23:59:59',
    ]);
    Generation::factory()->for($ada)->succeeded()->create([
        'provider_cost_nanos' => 54_050_000, 'price_nanos' => 67_562_500, 'completed_at' => '2026-11-16 00:00:00',
    ]);
    Generation::factory()->for($grace)->succeeded()->create([
        'provider_cost_nanos' => 11_200_000, 'price_nanos' => 14_000_000, 'completed_at' => '2026-11-16 09:30:00',
    ]);
    Generation::factory()->for($grace)->failed()->create(['completed_at' => '2026-11-16 10:00:00']);
    fakePixelforgeUsage([
        ['date' => '2026-11-14', 'generations' => 0, 'cost_usd' => '0.000000000'],
        ['date' => '2026-11-15', 'generations' => 1, 'cost_usd' => '0.305510000'],
        ['date' => '2026-11-16', 'generations' => 2, 'cost_usd' => '0.065250000'],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.report'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/Report')
        ->where('from', '2026-10-18')
        ->where('to', '2026-11-16')
        ->where('totals', [
            'generations' => 3,
            'charged_nanos' => 463_450_000,
            'provider_cost_nanos' => 370_760_000,
            'profit_nanos' => 92_690_000,
        ])
        ->where('pixelforgeTotal', ['generations' => 3, 'cost_nanos' => 370_760_000])
        ->has('days', 2)
        ->where('days.0.date', '2026-11-16')
        ->where('days.0.generations', 2)
        ->where('days.0.charged_nanos', 81_562_500)
        ->where('days.0.provider_cost_nanos', 65_250_000)
        ->where('days.0.profit_nanos', 16_312_500)
        ->where('days.0.pixelforge', ['generations' => 2, 'cost_nanos' => 65_250_000])
        ->where('days.0.users.0.user.name', 'Ada')
        ->where('days.0.users.0.charged_nanos', 67_562_500)
        ->where('days.0.users.1.user.name', 'Grace')
        ->where('days.1.date', '2026-11-15')
        ->where('days.1.provider_cost_nanos', 305_510_000)
        ->has('users', 2)
        ->where('users.0.user.name', 'Ada')
        ->where('users.0.generations', 2)
        ->where('users.0.charged_nanos', 449_450_000)
        ->where('users.0.provider_cost_nanos', 359_560_000)
        ->where('users.0.profit_nanos', 89_890_000)
        ->where('users.1.user.name', 'Grace')
        ->where('users.1.profit_nanos', 2_800_000)
    );
});

test('the report lists the latest generations Pixelforge knows about with their ids and costs', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    $ada = User::factory()->create(['name' => 'Ada']);
    Generation::factory()->for($ada)->succeeded()->create([
        'provider_id' => 'gen_succeeded', 'completed_at' => '2026-11-16 09:00:00',
    ]);
    Generation::factory()->for($ada)->failed()->create([
        'provider_id' => 'gen_failed', 'completed_at' => '2026-11-16 10:00:00',
    ]);
    $rejectedBeforePixelforgeSawIt = Generation::factory()->for($ada)->create();
    $rejectedBeforePixelforgeSawIt->markFailed('content_policy_violation', 'Rejected.');
    $rejectedBeforePixelforgeSawIt->save();
    fakePixelforgeUsage([]);

    $response = $this->actingAs($admin)->get(route('admin.report'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('generations', 2)
        ->where('generations.0.provider_id', 'gen_failed')
        ->where('generations.0.status', 'failed')
        ->where('generations.0.usage', null)
        ->where('generations.0.provider_cost_nanos', 0)
        ->where('generations.0.charged_nanos', 0)
        ->where('generations.1.provider_id', 'gen_succeeded')
        ->where('generations.1.user.name', 'Ada')
        ->where('generations.1.usage', [
            'input_text_tokens' => 10,
            'input_image_tokens' => 0,
            'output_tokens' => 1100,
            'reasoning_tokens' => 800,
        ])
        ->where('generations.1.provider_cost_nanos', 54_050_000)
        ->where('generations.1.charged_nanos', 67_562_500)
    );
});

test('a day Pixelforge billed that we have no record of still appears in the report', function () {
    $this->travelTo('2026-11-16 12:00:00');
    $admin = User::factory()->create(['is_admin' => true]);
    fakePixelforgeUsage([
        ['date' => '2026-11-16', 'generations' => 1, 'cost_usd' => '0.305510000'],
    ]);

    $response = $this->actingAs($admin)->get(route('admin.report'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('days', 1)
        ->where('days.0.date', '2026-11-16')
        ->where('days.0.provider_cost_nanos', 0)
        ->where('days.0.pixelforge', ['generations' => 1, 'cost_nanos' => 305_510_000])
    );
});

test('the report still loads when Pixelforge usage is unavailable', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Generation::factory()->succeeded()->create();
    Http::fake(['pixelforge.promptedit.dev/v1/usage*' => Http::response(status: 503)]);

    $response = $this->actingAs($admin)->get(route('admin.report'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('pixelforgeTotal', null)
        ->where('days.0.pixelforge', null)
        ->where('totals.generations', 1)
    );
});

test('a deleted account keeps its generations in the report', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create();
    Generation::factory()->for($user)->succeeded()->create();
    fakePixelforgeUsage([]);

    $user->delete();
    $response = $this->actingAs($admin)->get(route('admin.report'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('totals.provider_cost_nanos', 54_050_000)
        ->where('users.0.user', null)
    );
});

test('the make-admin command lets an existing user see the report', function () {
    $user = User::factory()->create(['email' => 'operator@example.com']);

    $this->artisan('user:make-admin', ['email' => 'operator@example.com'])->assertSuccessful();

    expect($user->refresh()->is_admin)->toBeTrue();
});

test('the make-admin command fails for an unknown email', function () {
    $this->artisan('user:make-admin', ['email' => 'nobody@example.com'])->assertFailed();
});
