<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('cloudflare:update-dns')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/cloudflare-ddns.log'));
