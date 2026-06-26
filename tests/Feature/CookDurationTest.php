<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use Carbon\Carbon;

test('cook began at is null until the first reading arrives', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    expect($cook->hasReadings())->toBeFalse()
        ->and($cook->getBeganAt())->toBeNull();
});

test('cook duration uses first and last reading times', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $start = Carbon::parse('2024-06-01 10:00:00');
    $end = Carbon::parse('2024-06-01 14:30:00');

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => $start,
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => $end,
        'probe_food' => 200,
        'probe_bbq' => 250,
    ]);

    expect($cook->getDurationSeconds())->toBe(16_200);
    expect($cook->getElapsedSeconds())->toBeGreaterThan(16_200);
});

test('sync ended at command sets ended at from last reading', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    $end = Carbon::parse('2024-06-01 14:30:00');

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => Carbon::parse('2024-06-01 10:00:00'),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => $end,
        'probe_food' => 200,
        'probe_bbq' => 250,
    ]);

    expect(Cook::syncAllEndedAtFromReadings())->toBe(1);

    $cook->refresh();

    expect($cook->ended_at?->equalTo($end))->toBeTrue();
    expect($cook->isActive())->toBeFalse();
});
