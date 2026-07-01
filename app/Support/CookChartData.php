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
            self::queryReadings($cook, includeNotes: true),
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
            $x = $reading->time->getTimestamp() - $start->getTimestamp();

            $foodPoint = [
                'x' => $x,
                'y' => ProbeTemperature::clean($reading->probe_food),
            ];

            $bbqPoint = [
                'x' => $x,
                'y' => ProbeTemperature::clean($reading->probe_bbq),
            ];

            if ($forDisplay) {
                if (filled($reading->note)) {
                    $foodPoint['note'] = $reading->note;
                    $bbqPoint['note'] = $reading->note;
                }
            } else {
                $foodPoint['id'] = $reading->id;
                $foodPoint['note'] = $reading->note;
                $bbqPoint['id'] = $reading->id;
                $bbqPoint['note'] = $reading->note;
            }

            $food[] = $foodPoint;
            $bbq[] = $bbqPoint;
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

        $noted = $readings->filter(fn (Reading $reading) => filled($reading->note))->values();
        $notedIds = $noted->pluck('id');

        $remainingSlots = max(0, $maxPoints - $noted->count());
        $candidates = $readings->reject(fn (Reading $reading) => $notedIds->contains($reading->id))->values();

        $uniform = ($remainingSlots > 0 && $candidates->isNotEmpty())
            ? self::uniformSample($candidates, min($remainingSlots, $candidates->count()))
            : collect();

        return $noted->merge($uniform)->sortBy('time')->values();
    }

    /**
     * @param  Collection<int, Reading>  $readings
     * @return Collection<int, Reading>
     */
    private static function uniformSample(Collection $readings, int $maxPoints): Collection
    {
        $count = $readings->count();

        if ($count <= $maxPoints) {
            return $readings;
        }

        if ($maxPoints === 1) {
            return collect([$readings->first()]);
        }

        $sampled = collect();

        for ($i = 0; $i < $maxPoints; $i++) {
            $index = (int) round($i * ($count - 1) / ($maxPoints - 1));
            $sampled->push($readings[$index]);
        }

        return $sampled->unique('id')->values();
    }
}
