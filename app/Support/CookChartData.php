<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\Reading;
use Illuminate\Support\Collection;

class CookChartData
{
    /**
     * @return array{startSecondsOfDay: int, food: array<int, array<string, mixed>>, bbq: array<int, array<string, mixed>>}
     */
    public static function empty(): array
    {
        return [
            'startSecondsOfDay' => 0,
            'food' => [],
            'bbq' => [],
        ];
    }

    /**
     * @return array{startSecondsOfDay: int, food: array<int, array<string, mixed>>, bbq: array<int, array<string, mixed>>}
     */
    public static function fromCook(Cook $cook): array
    {
        $readings = $cook->relationLoaded('readings')
            ? $cook->readings->sortBy('time')->values()
            : $cook->readings()->orderBy('time')->get();

        return self::fromReadings($readings);
    }

    /**
     * @param  Collection<int, Reading>  $readings
     * @return array{startSecondsOfDay: int, food: array<int, array<string, mixed>>, bbq: array<int, array<string, mixed>>}
     */
    public static function fromReadings(Collection $readings): array
    {
        if ($readings->isEmpty()) {
            return self::empty();
        }

        $start = $readings->first()->time;
        $food = [];
        $bbq = [];

        foreach ($readings as $reading) {
            $point = [
                'x' => $reading->time->getTimestamp() - $start->getTimestamp(),
                'id' => $reading->id,
                'note' => $reading->note,
            ];

            $food[] = [...$point, 'y' => self::cleanTemp($reading->probe_food)];
            $bbq[] = [...$point, 'y' => self::cleanTemp($reading->probe_bbq)];
        }

        return [
            'startSecondsOfDay' => $start->hour * 3600 + $start->minute * 60 + $start->second,
            'food' => $food,
            'bbq' => $bbq,
        ];
    }

    private static function cleanTemp(?int $value): ?int
    {
        return $value === 0 ? null : $value;
    }
}
