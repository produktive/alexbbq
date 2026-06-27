<?php

namespace App\Services;

use App\Models\Cook;
use App\Models\Reading;

class FakeMaverickSimulator
{
    public function __construct(
        private int $intervalSeconds = 12,
        private int $bbqTarget = 225,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            intervalSeconds: (int) config('maverick.fake.interval', 12),
            bbqTarget: (int) config('maverick.fake.bbq_target', 225),
        );
    }

    /**
     * @return array{probe_food: int, probe_bbq: int}
     */
    public function nextReading(Cook $cook, ?Reading $previous): array
    {
        $readingIndex = $cook->readings()->count();
        $elapsedMinutes = ($readingIndex * $this->intervalSeconds) / 60;

        return [
            'probe_bbq' => $this->bbqTemperature($elapsedMinutes, $previous?->probe_bbq),
            'probe_food' => $this->foodTemperature($elapsedMinutes, $previous?->probe_food),
        ];
    }

    private function bbqTemperature(float $elapsedMinutes, ?int $previous): int
    {
        $swing = (int) round(sin($elapsedMinutes / 12) * 8);
        $noise = random_int(-3, 3);
        $target = $this->bbqTarget + $swing + $noise;

        return $this->smooth($previous, $target, maxDelta: 12);
    }

    private function foodTemperature(float $elapsedMinutes, ?int $previous): int
    {
        $target = match (true) {
            $elapsedMinutes < 60 => 45 + ($elapsedMinutes / 60) * 105,
            $elapsedMinutes < 180 => 150 + (($elapsedMinutes - 60) / 120) * 15,
            default => min(203, 165 + (($elapsedMinutes - 180) / 60) * 38),
        };

        $target = (int) round($target + random_int(-2, 2));

        return $this->smooth($previous, $target, maxDelta: 8);
    }

    private function smooth(?int $previous, int $target, int $maxDelta): int
    {
        if ($previous === null) {
            return max(0, $target);
        }

        $delta = max(-$maxDelta, min($maxDelta, $target - $previous));

        return max(0, $previous + $delta);
    }
}
