<?php

namespace App\Services;

use App\Models\Reading;
use App\Models\User;
use App\Models\UserSettings;
use App\Notifications\TemperatureAlertNotification;
use App\Support\TemperatureAlertViolation;
use App\Support\WebPushResultRecorder;
use Illuminate\Support\Facades\Log;

class TemperatureAlertService
{
    public function evaluate(Reading $reading): void
    {
        $cook = $reading->cook;

        if ($cook === null || ! $cook->isActive()) {
            return;
        }

        $eligibleUsers = 0;

        User::query()
            ->whereHas('pushSubscriptions')
            ->whereHas('alertSettings')
            ->with('alertSettings')
            ->each(function (User $user) use ($reading, &$eligibleUsers): void {
                $eligibleUsers++;
                $this->evaluateForUser($user, $reading, route('home'));
            });

        Log::info('Evaluated temperature alerts for reading.', [
            'reading_id' => $reading->id,
            'cook_id' => $cook->id,
            'probe_food' => $reading->probe_food,
            'probe_bbq' => $reading->probe_bbq,
            'eligible_users' => $eligibleUsers,
        ]);
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

        $violation = TemperatureAlertViolation::message($probeLabel, $temperature, $min, $max);

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

        if (! $this->sendAlert($user, $violation, $url)) {
            return;
        }

        $settings->{$lastAlertColumn} = now();
        $settings->saveQuietly();
    }

    private function sendAlert(User $user, string $body, string $url): bool
    {
        try {
            [$recorder] = WebPushResultRecorder::measure(function () use ($user, $body, $url): void {
                $user->notify(new TemperatureAlertNotification(
                    title: 'Temperature Alert',
                    body: $body,
                    url: $url,
                ));
            });
        } catch (\Throwable $exception) {
            report($exception);

            return false;
        }

        return $recorder->sent > 0;
    }
}
