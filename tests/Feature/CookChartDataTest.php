<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Support\CookChartData;
use Carbon\Carbon;

test('display chart payload includes notes but omits reading ids', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => Carbon::parse('2024-06-01 10:00:00'),
        'probe_food' => 165,
        'probe_bbq' => 225,
        'note' => 'Wrapped',
    ]);

    $payload = CookChartData::forDisplay($cook);

    expect($payload['food'][0])->toBe(['x' => 0, 'y' => 165, 'note' => 'Wrapped'])
        ->and($payload['bbq'][0])->toBe(['x' => 0, 'y' => 225, 'note' => 'Wrapped'])
        ->and($payload['food'][0])->not->toHaveKey('id');
});

test('editor chart payload includes reading metadata', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now(),
    ]);

    $reading = Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => Carbon::parse('2024-06-01 10:00:00'),
        'probe_food' => 165,
        'probe_bbq' => 225,
        'note' => 'Wrapped',
    ]);

    $payload = CookChartData::forEditor($cook);

    expect($payload['food'][0])->toMatchArray([
        'x' => 0,
        'y' => 165,
        'id' => $reading->id,
        'note' => 'Wrapped',
    ]);
});

test('display chart payload downsamples large cooks while preserving noted readings', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now(),
    ]);

    $start = Carbon::parse('2024-06-01 10:00:00');

    for ($i = 0; $i < 2_500; $i++) {
        Reading::query()->create([
            'cook_id' => $cook->id,
            'time' => $start->copy()->addMinutes($i),
            'probe_food' => 150 + ($i % 50),
            'probe_bbq' => 220 + ($i % 30),
            'note' => $i === 1_999 ? 'Wrapped' : null,
        ]);
    }

    $payload = CookChartData::forDisplay($cook);

    expect($payload['food'])->toHaveCount(CookChartData::DISPLAY_MAX_POINTS)
        ->and($payload['food'][0])->toBe(['x' => 0, 'y' => 150])
        ->and(collect($payload['food'])->contains(fn (array $point) => ($point['note'] ?? null) === 'Wrapped'))->toBeTrue();
});

test('cook request cache is flushed after finishing an active cook', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $cook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => null,
    ]);

    expect(Cook::active()?->id)->toBe($cook->id);

    $cook->update(['ended_at' => now()]);
    Cook::flushRequestCache();

    expect(Cook::active())->toBeNull();
});
