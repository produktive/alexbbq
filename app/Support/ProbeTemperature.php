<?php

namespace App\Support;

class ProbeTemperature
{
    public static function clean(?int $value): ?int
    {
        return $value === 0 ? null : $value;
    }
}
