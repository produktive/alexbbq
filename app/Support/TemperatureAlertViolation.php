<?php

namespace App\Support;

use App\Models\UserSettings;

class TemperatureAlertViolation
{
    public static function message(string $probeLabel, int $temperature, int $min, int $max): ?string
    {
        if ($temperature <= 0) {
            return null;
        }

        if ($min > UserSettings::TEMPERATURE_OFF && $temperature < $min) {
            return "{$probeLabel} probe is {$temperature}°F, below your minimum of {$min}°F.";
        }

        if ($temperature > $max) {
            return "{$probeLabel} probe is {$temperature}°F, above your maximum of {$max}°F.";
        }

        return null;
    }
}
