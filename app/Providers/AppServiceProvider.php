<?php

namespace App\Providers;

use App\Models\Reading;
use App\Observers\ReadingObserver;
use App\Support\Theme;
use App\Support\WebPushResultRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Filament\Support\Facades\FilamentColor;
use Filament\Support\Facades\FilamentView;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use NotificationChannels\WebPush\Events\NotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Reading::observe(ReadingObserver::class);

        FilamentColor::register([
            'primary' => Theme::filamentPrimary(),
        ]);

        FilamentView::spa();

        $this->registerWebPushListeners();
        $this->configureDefaults();
    }

    protected function registerWebPushListeners(): void
    {
        Event::listen(NotificationSent::class, function (): void {
            WebPushResultRecorder::notifySent();
        });

        Event::listen(NotificationFailed::class, function (NotificationFailed $event): void {
            $report = $event->report;
            $reason = $report->getReason();

            WebPushResultRecorder::notifyFailed($reason);

            Log::warning('Web push delivery failed.', [
                'endpoint' => $event->subscription->endpoint,
                'reason' => $reason,
                'expired' => $report->isSubscriptionExpired(),
                'status_code' => $report->getResponse()?->getStatusCode(),
            ]);

            if ($report->isSubscriptionExpired() || str_contains($reason, '410 Gone')) {
                $event->subscription->delete();
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
