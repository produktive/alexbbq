<?php

use App\Events\CookReadingAdded;
use App\Events\LiveCookChanged;
use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use Illuminate\Support\Facades\Event;

test('home page shows idle state when no cooks exist', function () {
    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('No cooks yet');
});

test('home page shows most recent cook when none are active', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Older Brisket',
        'ended_at' => now()->subDays(2),
    ]);

    $recent = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Recent Ribs',
        'ended_at' => now()->subDay(),
    ]);

    Reading::query()->create([
        'cook_id' => $recent->id,
        'time' => now()->subDay(),
        'probe_food' => 195,
        'probe_bbq' => 225,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Recent Ribs');
    $response->assertDontSee('Older Brisket');
    $response->assertDontSee('No cooks yet');
    $response->assertDontSee('>Live<', false);
});

test('home page shows live chart for active cook', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    $response = $this->get(route('home'));

    $response->assertOk();
    $response->assertSee('Brisket');
    $response->assertDontSee('No cooks yet');
});

test('maverick can trigger reading broadcast with token', function () {
    config(['services.maverick.broadcast_token' => 'test-token']);

    Event::fake([CookReadingAdded::class]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Ribs',
    ]);

    $reading = Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 180,
        'probe_bbq' => 250,
    ]);

    $response = $this->postJson("/api/maverick/readings/{$reading->id}/broadcast", [], [
        'Authorization' => 'Bearer test-token',
    ]);

    $response->assertNoContent();

    Event::assertDispatched(CookReadingAdded::class);
});

test('artisan command can broadcast a reading', function () {
    Event::fake([CookReadingAdded::class]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Ribs',
    ]);

    $reading = Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 180,
        'probe_bbq' => 250,
    ]);

    $this->artisan('cook:broadcast-reading', ['reading' => $reading->id])
        ->assertSuccessful();

    Event::assertDispatched(CookReadingAdded::class);
});

test('artisan command can broadcast cook started and ended events', function () {
    Event::fake([LiveCookChanged::class]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Pork',
        'ended_at' => null,
    ]);

    $this->artisan('cook:broadcast-started', ['cook' => $cook->id])
        ->assertSuccessful();

    Event::assertDispatched(LiveCookChanged::class);

    $cook->update(['ended_at' => now()]);

    $this->artisan('cook:broadcast-ended', ['cook' => $cook->id])
        ->assertSuccessful();

    Event::assertDispatched(LiveCookChanged::class);
});

test('maverick broadcast endpoint rejects invalid token', function () {
    config(['services.maverick.broadcast_token' => 'test-token']);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Ribs',
    ]);

    $reading = Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 180,
        'probe_bbq' => 250,
    ]);

    $this->postJson("/api/maverick/readings/{$reading->id}/broadcast")
        ->assertUnauthorized();
});

test('active cook helper ignores ended cooks', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Finished',
        'ended_at' => now()->subHour(),
    ]);

    $active = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live',
        'ended_at' => null,
    ]);

    expect(Cook::active()?->id)->toBe($active->id);
});

test('live cook changed event can be broadcast for maverick', function () {
    config(['services.maverick.broadcast_token' => 'test-token']);

    Event::fake([LiveCookChanged::class]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Pork',
        'ended_at' => null,
    ]);

    $this->postJson("/api/maverick/cooks/{$cook->id}/started", [], [
        'Authorization' => 'Bearer test-token',
    ])->assertNoContent();

    Event::assertDispatched(LiveCookChanged::class);
});
