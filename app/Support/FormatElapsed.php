<?php

namespace App\Support;

use Carbon\Carbon;

class FormatElapsed
{
    public static function fromSeconds(int $seconds): string
    {
        $total = max(0, $seconds);
        $hours = intdiv($total, 3600);
        $minutes = intdiv($total % 3600, 60);
        $secs = $total % 60;

        if ($hours > 0) {
            return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
        }

        return sprintf('%d:%02d', $minutes, $secs);
    }

    public static function fromIso8601(string $iso8601): string
    {
        return self::fromSeconds((int) Carbon::parse($iso8601)->diffInSeconds(now()));
    }
}
