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
            'maverickRunning' => app(MaverickService::class)->isRunning(),
            'finishedCount' => Cook::finishedCount(),
        ]);
    }

    public function chartData(Cook $cook): JsonResponse
    {
        $cook->load('readings');

        return response()->json(CookChartData::fromCook($cook));
    }
}
