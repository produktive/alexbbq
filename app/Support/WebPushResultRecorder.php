<?php

namespace App\Support;

class WebPushResultRecorder
{
    /**
     * @var list<self>
     */
    private static array $stack = [];

    public int $sent = 0;

    public int $failed = 0;

    /**
     * @var list<string>
     */
    public array $failureReasons = [];

    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return array{0: self, 1: TReturn}
     */
    public static function measure(callable $callback): array
    {
        $recorder = new self;
        self::$stack[] = $recorder;

        try {
            return [$recorder, $callback()];
        } finally {
            array_pop(self::$stack);
        }
    }

    public static function notifySent(): void
    {
        foreach (self::$stack as $recorder) {
            $recorder->recordSent();
        }
    }

    public static function notifyFailed(string $reason): void
    {
        foreach (self::$stack as $recorder) {
            $recorder->recordFailed($reason);
        }
    }

    public function recordSent(): void
    {
        $this->sent++;
    }

    public function recordFailed(string $reason): void
    {
        $this->failed++;
        $this->failureReasons[] = $reason;
    }
}
