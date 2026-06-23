<?php

use App\Models\User;

test('guests can visit the home page', function () {
    $this->get(route('home'))->assertOk();
});

test('authenticated users can visit the home page', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get(route('home'))->assertOk();
});
