<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
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

    $ended = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Old Ribs',
        'ended_at' => now()->subHour(),
    ]);

    $component = Livewire::test('pages::home')
        ->assertSet('displayCookId', $ended->id);

    Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'New Brisket',
        'ended_at' => null,
    ]);

    $component->call('refreshChartData')
        ->assertSet('displayCookId', Cook::active()?->id);
});

test('home page poll refreshes chart data for active cook', function () {
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

    Livewire::test('pages::home')
        ->call('pollLiveCookUpdates')
        ->assertDispatched('cook-chart-updated');
});

test('home page poll switches to a newly started cook', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $ended = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Old Ribs',
        'ended_at' => now()->subHour(),
    ]);

    Livewire::test('pages::home')
        ->assertSet('displayCookId', $ended->id)
        ->tap(function () use ($smoker) {
            Cook::query()->create([
                'smoker_id' => $smoker->id,
                'title' => 'New Brisket',
                'ended_at' => null,
            ]);
        })
        ->call('pollLiveCookUpdates')
        ->assertSet('displayCookId', Cook::active()?->id);
});

test('live cook indicator poll syncs active cook state', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Livewire::test('live-cook-indicator')
        ->assertSet('cookId', $cook->id)
        ->tap(fn () => $cook->update(['ended_at' => now()]))
        ->call('pollLiveCookIndicator')
        ->assertSet('cookId', null);
});

test('cook view poll refreshes chart data for finished cook', function () {
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
        ->call('pollLiveCookView')
        ->assertNotDispatched('cook-chart-updated');
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
    $user = \App\Models\User::factory()->create();
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
        'pkill -x maverick' => Process::result(),
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

    app(\App\Services\MaverickService::class)->stop();

    $cook->refresh();

    expect($cook->ended_at)->not->toBeNull();
});

test('home page clears live state when cook stopped event fires', function () {
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

    Livewire::test('pages::home')
        ->assertSet('displayCookId', $cook->id)
        ->tap(fn () => $cook->update(['ended_at' => now()]))
        ->dispatch('cook-stopped')
        ->assertSet('displayCookId', $cook->id);
});
