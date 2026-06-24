<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Models\User;
use App\Models\UserSettings;
use App\Services\TemperatureAlertService;
use App\Support\WebPushResultRecorder;
use Illuminate\Console\Command;

class DiagnoseTemperatureAlerts extends Command
{
    protected $signature = 'alerts:diagnose
                            {reading? : Reading ID to evaluate (defaults to latest)}
                            {--send : Actually send notifications}
                            {--force : Ignore alert cooldown and clear last-sent timestamps}';

    protected $description = 'Inspect alert configuration and optionally evaluate a reading';

    public function handle(TemperatureAlertService $alerts): int
    {
        $reading = $this->resolveReading();

        if ($reading === null) {
            $this->error('No reading found.');

            return self::FAILURE;
        }

        $cook = $reading->cook;

        $this->line('Reading #'.$reading->id);
        $this->line('  Food probe: '.$reading->probe_food.'°F');
        $this->line('  BBQ probe: '.$reading->probe_bbq.'°F');
        $this->line('  Cook #'.($cook?->id ?? 'none').' active: '.($cook?->isActive() ? 'yes' : 'no'));

        $this->newLine();
        $this->line('VAPID public key configured: '.(filled(config('webpush.vapid.public_key')) ? 'yes' : 'no'));
        $this->line('VAPID subject: '.(config('webpush.vapid.subject') ?: '(missing)'));

        $users = User::query()
            ->whereHas('pushSubscriptions')
            ->whereHas('alertSettings')
            ->with(['alertSettings', 'pushSubscriptions'])
            ->get();

        $this->newLine();
        $this->line('Users eligible for alerts: '.$users->count());

        foreach ($users as $user) {
            $settings = $user->alertSettings;

            $subscriptionCount = $user->pushSubscriptions->count();

            $this->line("  {$user->email}");
            $this->line("    push subscriptions: {$subscriptionCount}");

            if ($subscriptionCount > 1) {
                $this->warn('    Multiple subscriptions detected. Disable and re-enable push on your device to keep only the current one.');
            }
            $this->line("    food range: {$settings->food_min}-{$settings->food_max}°F");
            $this->line("    bbq range: {$settings->bbq_min}-{$settings->bbq_max}°F");
            $this->line("    alert interval: {$settings->alert_interval_minutes} min");
            $this->line('    last bbq alert: '.($settings->last_bbq_alert_at?->toDateTimeString() ?? 'never'));
        }

        if ($users->isEmpty()) {
            $this->warn('No users have both push subscriptions and alert settings.');

            return self::FAILURE;
        }

        if ($cook === null || ! $cook->isActive()) {
            $this->warn('Cook is not active, so alerts will not fire.');

            return self::FAILURE;
        }

        if (! $this->option('send')) {
            $this->newLine();
            $this->info('Dry run only. Re-run with --send to evaluate and send notifications.');

            return self::SUCCESS;
        }

        if ($this->option('force')) {
            UserSettings::query()->update([
                'last_food_alert_at' => null,
                'last_bbq_alert_at' => null,
            ]);

            $this->line('Cleared alert cooldown timestamps.');
        }

        $this->newLine();
        $this->info('Evaluating alerts...');

        [$recorder] = WebPushResultRecorder::measure(fn () => $alerts->evaluate($reading));

        if ($recorder->sent > 0 && $recorder->failed === 0) {
            $this->info("Push delivery succeeded for {$recorder->sent} subscription(s).");
        } elseif ($recorder->sent > 0) {
            $this->warn("Push delivery partially succeeded ({$recorder->sent} ok, {$recorder->failed} failed).");
            $this->warn('Disable and re-enable push on your device to remove stale subscriptions.');
        } elseif ($recorder->failed > 0) {
            $this->error("Push delivery failed for all {$recorder->failed} subscription(s):");
            foreach (array_slice($recorder->failureReasons, 0, 5) as $failure) {
                $this->line('  - '.str($failure)->limit(120));
            }
            $this->warn('Disable and re-enable push notifications in the browser, then run this again.');
        } else {
            $this->warn('No push was attempted (probe in range, cooldown active, or no violation).');
            $this->line('Try again with --force if cooldown may be blocking sends.');
        }

        return $recorder->sent > 0 ? self::SUCCESS : self::FAILURE;
    }

    private function resolveReading(): ?Reading
    {
        $readingId = $this->argument('reading');

        if ($readingId !== null) {
            return Reading::query()->find($readingId);
        }

        return Reading::query()->latest('id')->first();
    }
}
