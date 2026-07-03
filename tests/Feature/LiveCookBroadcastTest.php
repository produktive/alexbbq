<?php

use App\Events\LiveCookUpdated;
use App\Models\Cook;
use App\Models\Smoker;
use App\Services\LiveCookBroadcast;
use Illuminate\Support\Facades\Http;

test('live cook updated event exposes camelCase payload for echo clients', function () {
    $event = LiveCookUpdated::reading(cookId: 42, beganAt: now()->toIso8601String());

    expect($event->type)->toBe('reading')
        ->and($event->cookId)->toBe(42)
        ->and($event->broadcastAs())->toBe('LiveCookUpdated')
        ->and($event->broadcastOn())->toHaveCount(1);
});

test('live cook broadcast posts reading events to the local reverb server', function () {
    Http::fake([
        '127.0.0.1:8080/*' => Http::response([]),
    ]);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'testkeytestkeytestke',
        'broadcasting.connections.reverb.secret' => 'testsecrettestsecrett',
        'broadcasting.connections.reverb.app_id' => '123456',
        'broadcasting.connections.reverb.options' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
        'broadcasting.connections.reverb.client_options' => [
            'verify' => false,
        ],
    ]);

    LiveCookBroadcast::reading(cookId: 7);

    Http::assertSent(function ($request) {
        if (! str_contains($request->url(), '/apps/123456/events')) {
            return false;
        }

        $body = json_decode($request->body(), true);

        if (! is_array($body) || ($body['name'] ?? null) !== 'LiveCookUpdated') {
            return false;
        }

        $payload = json_decode($body['data'] ?? '{}', true);

        return is_array($payload)
            && ($payload['type'] ?? null) === 'reading'
            && ($payload['cookId'] ?? null) === 7;
    });
});

test('evaluate cook alerts broadcasts a reading update', function () {
    Http::fake([
        '127.0.0.1:8080/*' => Http::response([]),
    ]);

    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'testkeytestkeytestke',
        'broadcasting.connections.reverb.secret' => 'testsecrettestsecrett',
        'broadcasting.connections.reverb.app_id' => '123456',
        'broadcasting.connections.reverb.options' => [
            'host' => '127.0.0.1',
            'port' => 8080,
            'scheme' => 'http',
            'useTLS' => false,
        ],
        'broadcasting.connections.reverb.client_options' => [
            'verify' => false,
        ],
    ]);

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

    Http::assertSent(fn ($request) => str_contains($request->url(), '/apps/123456/events'));
});
