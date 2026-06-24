<?php

namespace App\Console\Commands;

use App\Models\Reading;
use App\Models\User;
use App\Models\UserSettings;
use App\Services\TemperatureAlertService;
use Illuminate\Console\Command;

class DiagnoseTemperatureAlerts extends Command
{
    protected $signature = 'alerts:diagnose {reading? : Reading ID to evaluate (defaults to latest)} {--send : Actually send notifications}';

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

            $this->line("  {$user->email}");
            $this->line("    push subscriptions: {$user->pushSubscriptions->count()}");
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

        $this->newLine();
        $this->info('Evaluating alerts...');
        $alerts->evaluate($reading);
        $this->info('Done. Check storage/logs/laravel.log if nothing arrived on your device.');

        return self::SUCCESS;
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
