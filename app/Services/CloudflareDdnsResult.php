<?php

namespace App\Services;

class CloudflareDdnsResult
{
    public const STATUS_UNCHANGED = 'unchanged';

    public const STATUS_SYNCED = 'synced';

    public const STATUS_WOULD_UPDATE = 'would_update';

    public const STATUS_UPDATED = 'updated';

    private function __construct(
        public readonly string $status,
        public readonly string $ip,
        public readonly ?string $recordName = null,
        public readonly ?string $previousIp = null,
    ) {}

    public static function unchanged(string $ip): self
    {
        return new self(self::STATUS_UNCHANGED, $ip);
    }

    public static function synced(string $ip, string $recordName): self
    {
        return new self(self::STATUS_SYNCED, $ip, $recordName);
    }

    public static function wouldUpdate(string $ip, string $recordName, ?string $previousIp): self
    {
        return new self(self::STATUS_WOULD_UPDATE, $ip, $recordName, $previousIp);
    }

    public static function updated(string $ip, string $recordName, ?string $previousIp): self
    {
        return new self(self::STATUS_UPDATED, $ip, $recordName, $previousIp);
    }
}
