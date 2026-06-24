<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\User;
use App\Models\UserSettings;
use App\Notifications\TemperatureAlertNotification;

class TemperatureAlertService
{
    public function evaluate(Reading $reading): void
    {
        $cook = $reading->cook;

        if ($cook === null || ! $cook->isActive()) {
            return;
        }

        User::query()
            ->whereHas('pushSubscriptions')
            ->whereHas('alertSettings')
            ->with('alertSettings')
            ->each(function (User $user) use ($reading, $cook): void {
                $this->evaluateForUser($user, $reading, route('home'));
            });
    }

    private function evaluateForUser(User $user, Reading $reading, string $url): void
    {
        $settings = $user->alertSettings;

        if ($settings === null) {
            return;
        }

        $this->evaluateProbe(
            user: $user,
            settings: $settings,
            probeLabel: 'Food',
            temperature: (int) $reading->probe_food,
            min: $settings->food_min,
            max: $settings->food_max,
            lastAlertColumn: 'last_food_alert_at',
            url: $url,
        );

        $this->evaluateProbe(
            user: $user,
            settings: $settings,
            probeLabel: 'BBQ',
            temperature: (int) $reading->probe_bbq,
            min: $settings->bbq_min,
            max: $settings->bbq_max,
            lastAlertColumn: 'last_bbq_alert_at',
            url: $url,
        );
    }

    private function evaluateProbe(
        User $user,
        UserSettings $settings,
        string $probeLabel,
        int $temperature,
        int $min,
        int $max,
        string $lastAlertColumn,
        string $url,
    ): void {
        if ($temperature <= 0) {
            if ($settings->{$lastAlertColumn} !== null) {
                $settings->{$lastAlertColumn} = null;
                $settings->saveQuietly();
            }

            return;
        }

        $violation = $this->violationMessage($probeLabel, $temperature, $min, $max);

        if ($violation === null) {
            if ($settings->{$lastAlertColumn} !== null) {
                $settings->{$lastAlertColumn} = null;
                $settings->saveQuietly();
            }

            return;
        }

        $lastAlertAt = $settings->{$lastAlertColumn};

        if ($lastAlertAt !== null && $lastAlertAt->greaterThan(
            now()->subMinutes($settings->alert_interval_minutes)
        )) {
            return;
        }

        try {
            $user->notify(new TemperatureAlertNotification(
                title: 'Temperature Alert',
                body: $violation,
                url: $url,
            ));
        } catch (\Throwable $exception) {
            report($exception);

            return;
        }

        $settings->{$lastAlertColumn} = now();
        $settings->saveQuietly();
    }

    private function violationMessage(string $probeLabel, int $temperature, int $min, int $max): ?string
    {
        if ($min > UserSettings::TEMPERATURE_OFF && $temperature < $min) {
            return "{$probeLabel} probe is {$temperature}°F, below your minimum of {$min}°F.";
        }

        if ($temperature > $max) {
            return "{$probeLabel} probe is {$temperature}°F, above your maximum of {$max}°F.";
        }

        return null;
    }
}
