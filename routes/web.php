<?php

use App\Http\Controllers\PushSubscriptionController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages::home')->name('home');
Route::livewire('/cooks', 'pages::cooks.index')->name('cooks');
Route::view('/stats', 'stats')->name('stats');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('/cooks/new', 'pages::cooks.new')->name('cooks.new');
    Route::livewire('/cooks/{cook}/edit', 'pages::cooks.edit')->whereNumber('cook')->name('cooks.edit');
    Route::livewire('alerts', 'pages::alerts')->name('alerts');
    Route::livewire('smokers', 'pages::smokers')->name('smokers');
    Route::post('push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');
});

Route::livewire('/cooks/{cook}', 'pages::cooks.view')->whereNumber('cook')->name('cooks.view');

require __DIR__.'/settings.php';
