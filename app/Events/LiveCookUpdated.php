<?php

namespace App\Events;

use App\Models\Cook;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LiveCookUpdated implements ShouldBroadcastNow
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $type,
        public ?int $cookId = null,
        public ?string $beganAt = null,
        public bool $maverickRunning = false,
    ) {
        //
    }

    public static function started(Cook $cook, bool $maverickRunning = true): self
    {
        return new self(
            type: 'started',
            cookId: $cook->id,
            beganAt: $cook->getBeganAt()?->toIso8601String(),
            maverickRunning: $maverickRunning,
        );
    }

    public static function stopped(int $cookId): self
    {
        return new self(type: 'stopped', cookId: $cookId);
    }

    public static function reading(int $cookId, ?string $beganAt = null): self
    {
        return new self(type: 'reading', cookId: $cookId, beganAt: $beganAt);
    }

    public function broadcastOn(): array
    {
        return [new Channel('cooks')];
    }

    public function broadcastAs(): string
    {
        return 'LiveCookUpdated';
    }
}
