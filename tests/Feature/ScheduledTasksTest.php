<?php

use Illuminate\Console\Scheduling\Event;
use Illuminate\Support\Facades\Schedule;

test('cloudflare dns update is scheduled every five minutes', function () {
    $event = collect(Schedule::events())->first(
        fn (Event $event): bool => str_contains($event->command ?? '', 'cloudflare:update-dns'),
    );

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/5 * * * *');
});
