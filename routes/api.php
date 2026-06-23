<?php

use App\Http\Controllers\MaverickBroadcastController;
use App\Http\Middleware\VerifyMaverickToken;
use Illuminate\Support\Facades\Route;

Route::middleware(VerifyMaverickToken::class)->prefix('maverick')->group(function () {
    Route::post('readings/{reading}/broadcast', [MaverickBroadcastController::class, 'reading'])
        ->whereNumber('reading');

    Route::post('cooks/{cook}/started', [MaverickBroadcastController::class, 'cookStarted'])
        ->whereNumber('cook');

    Route::post('cooks/{cook}/ended', [MaverickBroadcastController::class, 'cookEnded'])
        ->whereNumber('cook');
});
