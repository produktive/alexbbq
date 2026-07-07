<?php

use App\Http\Controllers\LiveCookController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\WebAppManifestController;
use App\Support\AuthRedirect;
use App\Support\CookStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::get('/manifest.webmanifest', WebAppManifestController::class)->name('manifest');
Route::livewire('/cooks', 'pages::cooks.index')->name('cooks');
Route::get('/stats', fn () => view('stats', ['stats' => CookStats::summarize()]))->name('stats');

Route::get('/live/cook-status', [LiveCookController::class, 'status'])->name('live.cook-status');
Route::get('/cooks/{cook}/chart-data', [LiveCookController::class, 'chartData'])->whereNumber('cook')->name('cooks.chart-data');

Route::middleware('auth')->group(function () {
    Route::livewire('/cooks/new', 'pages::cooks.new')->name('cooks.new');
    Route::livewire('/cooks/{cook}/edit', 'pages::cooks.edit')->whereNumber('cook')->name('cooks.edit');
    Route::livewire('alerts', 'pages::alerts')->name('alerts');
    Route::livewire('smokers', 'pages::smokers')->name('smokers');
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
    Route::get('/live/alert-badge', [LiveCookController::class, 'alertBadge'])->name('live.alert-badge');
});

Route::livewire('/cooks/{cook}', 'pages::cooks.view')->whereNumber('cook')->name('cooks.view');

Route::middleware('guest')->get('/login/intended', function (Request $request) {
    $redirect = $request->query('redirect');

    if (! is_string($redirect) || $redirect === '') {
        $redirect = AuthRedirect::pathFromReferer($request);
    }

    AuthRedirect::storeIntendedUrl($redirect, $request);

    $intended = $request->session()->get('url.intended');

    return redirect()->route('login', $intended ? ['redirect' => $intended] : []);
})->name('login.intended');

require __DIR__.'/settings.php';
