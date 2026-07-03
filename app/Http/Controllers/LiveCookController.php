<?php

namespace App\Http\Controllers;

use App\Models\Cook;
use App\Services\MaverickService;
use App\Support\CookChartData;
use App\Support\TemperatureAlertViolation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LiveCookController extends Controller
{
    public function status(): JsonResponse
    {
        $maverick = app(MaverickService::class);
        $maverick->reconcileOrphanedActiveCook();

        $cook = Cook::active();

        return response()->json([
            'activeCookId' => $cook?->id,
            'beganAt' => $cook?->getBeganAt()?->toIso8601String(),
            'beganAtLabel' => $cook?->getBeganAt()?->format('F j, Y \a\t g:i A'),
            'waitingForReading' => $cook !== null && $cook->isActive() && ! $cook->hasReadings(),
            'maverickRunning' => $maverick->isRunning(),
            'finishedCount' => Cook::finishedCount(),
        ]);
    }

    public function alertBadge(Request $request): JsonResponse
    {
        $settings = $request->user()?->alertSettings;

        if ($settings === null) {
            return response()->json(['count' => 0]);
        }

        $cook = Cook::active();

        if ($cook === null) {
            return response()->json(['count' => 0]);
        }

        $reading = $cook->latestReading();

        if ($reading === null) {
            return response()->json(['count' => 0]);
        }

        return response()->json([
            'count' => TemperatureAlertViolation::countForTemperatures(
                probeFood: (int) $reading->probe_food,
                probeBbq: (int) $reading->probe_bbq,
                foodMin: $settings->food_min,
                foodMax: $settings->food_max,
                bbqMin: $settings->bbq_min,
                bbqMax: $settings->bbq_max,
            ),
        ]);
    }

    public function chartData(Cook $cook, Request $request): JsonResponse
    {
        if (
            $request->boolean('editor')
            && auth()->check()
            && $cook->isOwnedBy(auth()->id())
        ) {
            return response()->json(CookChartData::forEditor($cook));
        }

        return response()->json(CookChartData::forDisplay($cook));
    }
}
