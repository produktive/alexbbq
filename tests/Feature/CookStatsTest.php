<?php

use App\Models\Cook;
use App\Models\Reading;
use App\Models\Smoker;
use App\Support\CookStats;
use Carbon\Carbon;

test('stats page is publicly accessible', function () {
    $response = $this->get(route('stats'));

    $response->assertOk();
    $response->assertSee('Cook Statistics');
});

test('stats page displays cook summary cards', function () {
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
    ]);

    Reading::query()->create([
        'cook_id' => $cook->id,
        'time' => Carbon::parse('2024-06-01 14:30:00'),
        'probe_food' => 200,
        'probe_bbq' => 250,
    ]);

    $response = $this->get(route('stats'));

    $response->assertOk();
    $response->assertSee('Cook Statistics');
    $response->assertSee('Total Cooks');
    $response->assertSee('Total Cook Time');
    $response->assertSee('Average Cook Time');
    $response->assertSee('Total Readings');
    $response->assertSee('1');
    $response->assertSee('2');
});

test('cook stats summarize totals and averages from readings', function () {
    $smoker = Smoker::query()->create(['name' => 'Backyard']);

    $firstCook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Brisket',
        'ended_at' => now(),
    ]);

    Reading::query()->create([
        'cook_id' => $firstCook->id,
        'time' => Carbon::parse('2024-06-01 10:00:00'),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    Reading::query()->create([
        'cook_id' => $firstCook->id,
        'time' => Carbon::parse('2024-06-01 12:00:00'),
        'probe_food' => 180,
        'probe_bbq' => 240,
    ]);

    $secondCook = Cook::query()->create([
        'smoker_id' => $smoker->id,
        'title' => 'Ribs',
        'ended_at' => now(),
    ]);

    Reading::query()->create([
        'cook_id' => $secondCook->id,
        'time' => Carbon::parse('2024-06-02 10:00:00'),
        'probe_food' => 165,
        'probe_bbq' => 225,
    ]);

    Reading::query()->create([
        'cook_id' => $secondCook->id,
        'time' => Carbon::parse('2024-06-02 14:00:00'),
        'probe_food' => 190,
        'probe_bbq' => 250,
    ]);

    $stats = CookStats::summarize();

    expect($stats->totalCooks)->toBe(2)
        ->and($stats->totalReadings)->toBe(4)
        ->and($stats->totalDurationSeconds)->toBe(21_600)
        ->and($stats->averageDurationSeconds)->toBe(10_800);
});

test('cook stats duration labels respect cascade limits', function () {
    $longDuration = (365 * 86400) + (60 * 86400) + (7 * 86400) + (5 * 3600) + (30 * 60) + 15;
    $averageDuration = (4 * 3600) + (30 * 60) + 15;

    expect(CookStats::formatDuration($longDuration, maxCascades: 3))->toBe("1 year\n3 months\n1 week")
        ->and(CookStats::formatDuration($averageDuration, maxCascades: 2))->toBe("4 hours\n30 minutes");
});
