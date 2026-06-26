<?php

use App\Support\FormatElapsed;
use Carbon\Carbon;

test('format elapsed renders minutes and seconds', function () {
    expect(FormatElapsed::fromSeconds(0))->toBe('0:00')
        ->and(FormatElapsed::fromSeconds(65))->toBe('1:05')
        ->and(FormatElapsed::fromSeconds(3661))->toBe('1:01:01');
});

test('format elapsed renders from iso8601 began at', function () {
    Carbon::setTestNow('2024-06-01 12:30:00');

    expect(FormatElapsed::fromIso8601('2024-06-01T10:00:00+00:00'))->toBe('2:30:00');
});
