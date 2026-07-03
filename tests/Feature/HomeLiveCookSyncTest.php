<?php

use App\Models\Cook;
use App\Models\Smoker;
use Livewire\Livewire;

test('home page syncs to the active cook from live status updates', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $finished = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Finished Brisket',
        'ended_at' => now()->subHour(),
    ]);

    $active = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Live Brisket',
        'ended_at' => null,
    ]);

    Cook::flushRequestCache();

    Livewire::test('pages::home')
        ->assertSet('displayCookId', $active->id)
        ->call('syncFromLiveStatus', $finished->id)
        ->assertSet('displayCookId', $finished->id)
        ->call('syncFromLiveStatus', $active->id)
        ->assertSet('displayCookId', $active->id);
});
