<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\Reading;
use Illuminate\Support\Collection;

class CookChartData
{
    public const DISPLAY_MAX_POINTS = 2_000;

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
    public static function forDisplay(Cook $cook): array
    {
        return self::fromReadings(
            self::queryReadings($cook, includeNotes: false),
            forDisplay: true,
        );
    }

    /**
     * @return array{startSecondsOfDay: int, food: array<int, array<string, mixed>>, bbq: array<int, array<string, mixed>>}
     */
    public static function forEditor(Cook $cook): array
    {
        return self::fromReadings(
            self::queryReadings($cook, includeNotes: true),
            forDisplay: false,
        );
    }

    /**
     * @return array{startSecondsOfDay: int, food: array<int, array<string, mixed>>, bbq: array<int, array<string, mixed>>}
     */
    public static function fromCook(Cook $cook): array
    {
        return self::forEditor($cook);
    }

    /**
     * @return Collection<int, Reading>
     */
    private static function queryReadings(Cook $cook, bool $includeNotes): Collection
    {
        if ($cook->relationLoaded('readings')) {
            return $cook->readings->sortBy('time')->values();
        }

        $columns = ['id', 'time', 'probe_food', 'probe_bbq'];

        if ($includeNotes) {
            $columns[] = 'note';
        }

        return $cook->readings()
            ->orderBy('time')
            ->get($columns);
    }

    /**
     * @param  Collection<int, Reading>  $readings
     * @return array{startSecondsOfDay: int, food: array<int, array<string, mixed>>, bbq: array<int, array<string, mixed>>}
     */
    public static function fromReadings(Collection $readings, bool $forDisplay = false): array
    {
        if ($readings->isEmpty()) {
            return self::empty();
        }

        if ($forDisplay && $readings->count() > self::DISPLAY_MAX_POINTS) {
            $readings = self::downsample($readings, self::DISPLAY_MAX_POINTS);
        }

        $start = $readings->first()->time;
        $food = [];
        $bbq = [];

        foreach ($readings as $reading) {
            $point = [
                'x' => $reading->time->getTimestamp() - $start->getTimestamp(),
            ];

            if (! $forDisplay) {
                $point['id'] = $reading->id;
                $point['note'] = $reading->note;
            }

            $food[] = [...$point, 'y' => self::cleanTemp($reading->probe_food)];
            $bbq[] = [...$point, 'y' => self::cleanTemp($reading->probe_bbq)];
        }

        return [
            'startSecondsOfDay' => $start->hour * 3600 + $start->minute * 60 + $start->second,
            'food' => $food,
            'bbq' => $bbq,
        ];
    }

    /**
     * @param  Collection<int, Reading>  $readings
     * @return Collection<int, Reading>
     */
    public static function downsample(Collection $readings, int $maxPoints): Collection
    {
        $count = $readings->count();

        if ($count <= $maxPoints) {
            return $readings;
        }

        $sampled = collect();

        for ($i = 0; $i < $maxPoints; $i++) {
            $index = (int) round($i * ($count - 1) / ($maxPoints - 1));
            $sampled->push($readings[$index]);
        }

        return $sampled->unique('id')->values();
    }

    private static function cleanTemp(?int $value): ?int
    {
        return $value === 0 ? null : $value;
    }
}
