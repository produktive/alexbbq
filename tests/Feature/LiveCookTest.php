<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Models\User;
use App\Services\MaverickService;
use Illuminate\Support\Facades\Process;
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
    $response->assertDontSee('No cooks yet');
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
        ->assertJsonPath('food.1.y', 170);
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
            'maverickRunning' => false,
            'finishedCount' => 0,
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

test('cook view renders finished cook chart', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subHour(),
    ]);

    Reading::withoutEvents(fn () => Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->subHour(),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]));

    Livewire::test('pages::cooks.view', ['cook' => $cook])
        ->assertOk()
        ->assertSee('Brisket');
});

test('menu cook count excludes the active cook', function () {
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

    expect(Cook::finishedCount())->toBe(1)
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

test('home page hides view full cook link while cook is live', function () {
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
        ->assertDontSee('View Full Cook');
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

test('maverick service stop finishes the active cook', function () {
    Process::fake([
        'sudo -n * stop' => Process::result(),
    ]);

    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->subMinutes(5),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    app(MaverickService::class)->stop();

    $cook->refresh();

    expect($cook->ended_at)->not->toBeNull();
});

test('maverick service start returns false when process does not launch', function () {
    Process::fake([
        'sudo -n * start' => Process::result(),
        'sudo -n * status' => Process::result(exitCode: 1),
    ]);

    expect(app(MaverickService::class)->start())->toBeFalse();
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
        ->assertSee('View Full Cook');
});
