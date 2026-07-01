<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;

test('cook view page lazy loads chart data', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subHour(),
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->subHours(2),
        'probe_food' => 203,
        'probe_bbq' => 250,
    ]);

    $response = $this->get(route('cooks.view', $cook));

    $response->assertOk();
    $response->assertSee('Brisket');
    $response->assertSee('Loading chart…');
    $response->assertDontSee('"y":203', false);
    $response->assertDontSee('startSecondsOfDay', false);
});

test('chart data endpoint returns display payload with notes for guests', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now()->subHour(),
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => now()->subHours(2),
        'probe_food' => 203,
        'probe_bbq' => 250,
        'note' => 'Wrapped',
    ]);

    $this->getJson(route('cooks.chart-data', ['cook' => $cook, 'editor' => true]))
        ->assertOk()
        ->assertJsonPath('food.0', ['x' => 0, 'y' => 203, 'note' => 'Wrapped'])
        ->assertJsonMissingPath('food.0.id');
});
