<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Models\User;
use Livewire\Livewire;

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
    $response->assertSee('165°');
    $response->assertSee('225°');
    $response->assertDontSee('No cooks yet');
});

test('cook latest probe temps return most recent reading values', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->subMinutes(10),
        'probe_food' => 150,
        'probe_bbq' => 210,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 172,
        'probe_bbq' => 228,
    ]);

    expect($cook->fresh()->latestProbeTemps())->toBe([
        'food' => 172,
        'bbq' => 228,
    ]);
});

test('home page syncs display cook during hydration', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Old Ribs',
        'ended_at' => now()->subHour(),
    ]);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'New Brisket',
        'ended_at' => null,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('New Brisket')
        ->assertDontSee('Old Ribs');
});

test('home page lazy loads chart data instead of embedding readings', function () {
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
    $response->assertSee('Loading chart…');
    $response->assertSee('Brisket');
    $response->assertDontSee('"y":165', false);
    $response->assertDontSee('startSecondsOfDay', false);
});

test('live cook chart data endpoint returns chart payload', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now(),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]));

    Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->addMinute(),
        'probe_food' => 170,
        'probe_bbq' => 230,
    ]));

    $this->getJson(route('cooks.chart-data', $cook))
        ->assertOk()
        ->assertJsonStructure(['startSecondsOfDay', 'food', 'bbq'])
        ->assertJsonPath('food.1.y', 170)
        ->assertJsonPath('food.0', ['x' => 0, 'y' => 165]);
});

test('live cook status endpoint returns active cook state', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $this->getJson(route('live.cook-status'))
        ->assertOk()
        ->assertJson([
            'activeCookId' => $cook->id,
            'beganAt' => null,
            'waitingForReading' => true,
            'maverickRunning' => false,
            'finishedCount' => 0,
        ]);
});

test('live cook status endpoint returns began at after first reading', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $start = now()->subMinutes(5);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => $start,
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    $this->getJson(route('live.cook-status'))
        ->assertOk()
        ->assertJson([
            'activeCookId' => $cook->id,
            'beganAt' => $start->toIso8601String(),
            'waitingForReading' => false,
        ]);
});

test('live cook status endpoint returns null state when no active cook', function () {
    $this->getJson(route('live.cook-status'))
        ->assertOk()
        ->assertJson([
            'activeCookId' => null,
            'beganAt' => null,
            'maverickRunning' => false,
            'finishedCount' => 0,
        ]);
});

test('live cook indicator shows active cook on mount', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Livewire::test('live-cook-indicator')
        ->assertSet('cookId', $cook->id);
});

test('cooks nav item updates count from status sync', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Finished Ribs',
        'ended_at' => now()->subDay(),
    ]);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    Livewire::test('cooks-nav-item')
        ->assertSet('count', 1)
        ->call('syncCountFromStatus', 2)
        ->assertSet('count', 2);
});

test('cook button syncs live state from status payload', function () {
    Livewire::test('cook-button')
        ->assertSet('live', false)
        ->call('syncLiveFromStatus', 1, true)
        ->assertSet('live', true)
        ->call('syncLiveFromStatus', null, false)
        ->assertSet('live', false);
});

test('active cook helpers ignore ended cooks when counting', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Finished Ribs',
        'ended_at' => now()->subDay(),
    ]);

    $active = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    expect(Cook::active()?->id)->toBe($active->id)
        ->and(Cook::finishedCount())->toBe(1)
        ->and(Cook::count())->toBe(2);
});

test('active cook is hidden from cooks index', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    $finished = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Finished Ribs',
        'ended_at' => now()->subDay(),
    ]);

    $this->get(route('cooks'))
        ->assertOk()
        ->assertSee('Finished Ribs')
        ->assertDontSee('Live Brisket');
});

test('active cook view page returns not found', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    $this->get(route('cooks.view', $cook))->assertNotFound();
});

test('active cook edit page returns not found', function () {
    $user = User::factory()->create();
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    $this->actingAs($user)
        ->get(route('cooks.edit', $cook))
        ->assertNotFound();
});

test('home page hides view cook page link while cook is live', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Reading::query()->create([
        'cook_id' => Cook::active()->id,
        'time' => now(),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Brisket')
        ->assertDontSee('View Cook Page');
});

test('home page shows finished cook after active cook ends', function () {
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

    $cook->update(['ended_at' => now()]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Brisket')
        ->assertSee('View Cook Page');
});
