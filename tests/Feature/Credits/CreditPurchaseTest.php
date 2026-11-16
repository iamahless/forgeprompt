<?php

use App\Models\Generation;
use App\Models\User;

test('guests cannot buy credits', function () {
    $response = $this->post(route('credits.store'), ['amount' => 10]);

    $response->assertRedirect(route('login'));
});

test('buying a credit pack adds that many dollars to the balance', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->from(route('dashboard'))->post(route('credits.store'), ['amount' => 10]);

    $response->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));
    expect($user->credits())->toBe([
        'balance' => 10_000_000_000,
        'reserved' => 0,
        'available' => 10_000_000_000,
    ]);
});

test('an amount that is not a credit pack is rejected', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('credits.store'), ['amount' => 1_000_000]);

    $response->assertSessionHasErrors(['amount' => 'The selected amount is invalid.']);
    expect($user->creditPurchases()->count())->toBe(0);
});

test('the balance is purchases minus charges, with in-flight generations held in reserve', function () {
    $user = User::factory()->create();
    $user->creditPurchases()->create(['amount_nanos' => 1_000_000_000]);
    Generation::factory()->for($user)->succeeded()->create(['price_nanos' => 381_887_500]);
    Generation::factory()->for($user)->failed()->create();
    Generation::factory()->for($user)->create(['max_price_nanos' => 86_325_000]);
    Generation::factory()->succeeded()->create();

    expect($user->credits())->toBe([
        'balance' => 618_112_500,
        'reserved' => 86_325_000,
        'available' => 531_787_500,
    ]);
});
