<?php

use App\Support\WebPushResultRecorder;

test('web push result recorder tracks nested measure calls', function () {
    [$outer] = WebPushResultRecorder::measure(function () {
        WebPushResultRecorder::measure(function () {
            WebPushResultRecorder::notifySent();
        });
    });

    expect($outer->sent)->toBe(1)
        ->and($outer->failed)->toBe(0);
});

test('web push result recorder tracks failures in nested measure calls', function () {
    [$outer] = WebPushResultRecorder::measure(function () {
        WebPushResultRecorder::measure(function () {
            WebPushResultRecorder::notifyFailed('410 Gone');
        });
    });

    expect($outer->sent)->toBe(0)
        ->and($outer->failed)->toBe(1)
        ->and($outer->failureReasons)->toBe(['410 Gone']);
});
