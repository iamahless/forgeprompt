<?php

use App\Models\Generation;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('users only see their own generations, without what the provider charged us', function () {
    $user = User::factory()->create();
    $mine = Generation::factory()->for($user)->succeeded()->create();
    Generation::factory()->succeeded()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Dashboard')
        ->has('generations.data', 1)
        ->where('generations.data.0.id', $mine->id)
        ->where('generations.data.0.price_nanos', 67_562_500)
        ->missing('generations.data.0.provider_cost_nanos')
        ->where('credits.balance', -67_562_500)
    );
});
