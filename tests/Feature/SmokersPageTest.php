<?php

use App\Models\User;

test('smokers page renders on a fresh install', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('smokers'))
        ->assertOk()
        ->assertSee('No Smokers')
        ->assertSee('Add New Smoker');
});
