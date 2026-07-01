<?php

namespace App\Support;

use App\Models\Cook;
use App\Models\Reading;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;

class CookStats
{
    public static function summarize(): CookStatsSummary
    {
        $totalCooks = Cook::query()->count();
        $totalReadings = Reading::query()->count();

        $durationExpression = self::durationSecondsExpression();

        $durationsQuery = DB::table('readings')
            ->selectRaw("cook_id, {$durationExpression} as cook_seconds")
            ->groupBy('cook_id');

        $durationStats = DB::query()
            ->fromSub($durationsQuery, 'durations')
            ->selectRaw('COALESCE(SUM(cook_seconds), 0) as total_seconds, COUNT(*) as cooks_with_readings')
            ->first();

        $totalDurationSeconds = (int) ($durationStats->total_seconds ?? 0);
        $cooksWithReadings = (int) ($durationStats->cooks_with_readings ?? 0);

        $averageDurationSeconds = $cooksWithReadings > 0
            ? (int) round($totalDurationSeconds / $cooksWithReadings)
            : 0;

        return new CookStatsSummary(
            totalCooks: $totalCooks,
            totalReadings: $totalReadings,
            totalDurationSeconds: $totalDurationSeconds,
            averageDurationSeconds: $averageDurationSeconds,
        );
    }

    public static function formatDuration(int $seconds, ?int $maxCascades = null): string
    {
        if ($seconds <= 0) {
            return '0 seconds';
        }

        $parts = self::cascadeParts(CarbonInterval::seconds($seconds)->cascade());

        if ($maxCascades !== null) {
            $parts = array_slice($parts, 0, $maxCascades);
        }

        return implode("\n", $parts);
    }

    /**
     * @return list<string>
     */
    private static function cascadeParts(CarbonInterval $interval): array
    {
        $units = [
            [$interval->years, 'year', 'years'],
            [$interval->months, 'month', 'months'],
            [$interval->weeks, 'week', 'weeks'],
            [$interval->dayzExcludeWeeks, 'day', 'days'],
            [$interval->hours, 'hour', 'hours'],
            [$interval->minutes, 'minute', 'minutes'],
            [$interval->seconds, 'second', 'seconds'],
        ];

        $parts = [];

        foreach ($units as [$value, $singular, $plural]) {
            $amount = (int) $value;

            if ($amount <= 0) {
                continue;
            }

            $parts[] = $amount.' '.($amount === 1 ? $singular : $plural);
        }

        return $parts;
    }

    private static function durationSecondsExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "CAST((strftime('%s', MAX(time)) - strftime('%s', MIN(time))) AS INTEGER)",
            'mysql' => 'TIMESTAMPDIFF(SECOND, MIN(time), MAX(time))',
            'pgsql' => 'CAST(EXTRACT(EPOCH FROM (MAX(time) - MIN(time))) AS INTEGER)',
            default => '0',
        };
    }
}
