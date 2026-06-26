<?php

namespace App\Http\Controllers;

use App\Models\Cook;
use App\Services\MaverickService;
use App\Support\CookChartData;
use Illuminate\Http\JsonResponse;

class LiveCookController extends Controller
{
    public function status(): JsonResponse
    {
        $cook = Cook::active();

        return response()->json([
            'activeCookId' => $cook?->id,
            'beganAt' => $cook?->getBeganAt()?->toIso8601String(),
            'beganAtLabel' => $cook?->getBeganAt()?->format('F j, Y \a\t g:i A'),
            'waitingForReading' => $cook !== null && $cook->isActive() && ! $cook->hasReadings(),
            'maverickRunning' => app(MaverickService::class)->isRunning(),
            'finishedCount' => Cook::finishedCount(),
        ]);
    }

    public function chartData(Cook $cook): JsonResponse
    {
        return response()->json(CookChartData::forDisplay($cook));
    }
}
