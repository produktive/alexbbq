<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;

test('cook view page renders chart data', function () {
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
});
