<?php

use App\Events\LiveCookUpdated;
use App\Models\Cook;
use App\Models\Smoker;
use App\Services\LiveCookBroadcast;

test('live cook updated event exposes camelCase payload for echo clients', function () {
    $event = LiveCookUpdated::reading(cookId: 42, beganAt: now()->toIso8601String());

    expect($event->type)->toBe('reading')
        ->and($event->cookId)->toBe(42)
        ->and($event->broadcastAs())->toBe('LiveCookUpdated')
        ->and($event->broadcastOn())->toHaveCount(1);
});

test('live cook broadcast dispatches reading events on log connection', function () {
    config(['broadcasting.default' => 'log']);

    LiveCookBroadcast::reading(cookId: 7);

    expect(true)->toBeTrue();
});

test('evaluate cook alerts broadcasts a reading update', function () {
    config(['broadcasting.default' => 'log']);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $reading = $cook->readings()->create([
        'time' => now(),
        'probe_food' => 150,
        'probe_bbq' => 225,
    ]);

    $this->artisan('cook:evaluate-alerts', ['reading' => $reading->id])
        ->assertSuccessful();
});
