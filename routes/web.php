<?php

use App\Http\Controllers\LiveCookController;
use App\Http\Controllers\PushSubscriptionController;
use App\Support\AuthRedirect;
use App\Support\CookStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/cooks', 'pages::cooks.index')->name('cooks');
Route::get('/stats', fn () => view('stats', ['stats' => CookStats::summarize()]))->name('stats');

Route::get('/live/cook-status', [LiveCookController::class, 'status'])->name('live.cook-status');
Route::get('/cooks/{cook}/chart-data', [LiveCookController::class, 'chartData'])->whereNumber('cook')->name('cooks.chart-data');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/cooks/new', 'pages::cooks.new')->name('cooks.new');
    Route::livewire('/cooks/{cook}/edit', 'pages::cooks.edit')->whereNumber('cook')->name('cooks.edit');
    Route::livewire('alerts', 'pages::alerts')->name('alerts');
    Route::livewire('smokers', 'pages::smokers')->name('smokers');
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
});

Route::livewire('/cooks/{cook}', 'pages::cooks.view')->whereNumber('cook')->name('cooks.view');

Route::middleware('guest')->post('/login/intended', function (Request $request) {
    AuthRedirect::storeIntendedUrl($request->input('redirect'), $request);

    return redirect()->route('login');
})->name('login.intended');

require __DIR__.'/settings.php';
