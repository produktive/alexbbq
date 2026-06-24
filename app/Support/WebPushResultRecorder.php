<?php

namespace App\Support;

class WebPushResultRecorder
{
    private static ?self $active = null;

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
        self::$active = $recorder;

        try {
            return [$recorder, $callback()];
        } finally {
            self::$active = null;
        }
    }

    public static function active(): ?self
    {
        return self::$active;
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
