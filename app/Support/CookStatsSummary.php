<?php

namespace App\Support;

readonly class CookStatsSummary
{
    public function __construct(
        public int $totalCooks,
        public int $totalReadings,
        public int $totalDurationSeconds,
        public int $averageDurationSeconds,
    ) {}

    public function totalDurationLabel(): string
    {
        return CookStats::formatDuration($this->totalDurationSeconds, maxCascades: 3);
    }

    public function averageDurationLabel(): string
    {
        return CookStats::formatDuration($this->averageDurationSeconds, maxCascades: 2);
    }
}
