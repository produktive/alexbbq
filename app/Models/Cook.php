<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cook extends Model implements HasRichContent
{
    use InteractsWithRichContent;

    public const DESCRIPTION_ATTACHMENTS_DISK = 'public';

    public const DESCRIPTION_ATTACHMENTS_DIRECTORY = 'cooks/descriptions';

    public const DESCRIPTION_ATTACHMENTS_VISIBILITY = 'public';

    public const DESCRIPTION_ATTACHMENT_MAX_BYTES = 2_097_152;

    public const DESCRIPTION_ATTACHMENT_MAX_UPLOAD_KB = 2048;

    public const DESCRIPTION_ATTACHMENT_SOURCE_MAX_UPLOAD_KB = 12_288;

    public const DESCRIPTION_ATTACHMENT_MAX_WIDTH = 1920;

    public const DESCRIPTION_ATTACHMENT_MAX_HEIGHT = 1920;

    protected $fillable = [
        'user_id',
        'smoker_id',
        'title',
        'description',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'ended_at' => 'datetime',
        ];
    }

    protected function setUpRichContent(): void
    {
        $this->registerRichContent('description')
            ->fileAttachmentsDisk(self::DESCRIPTION_ATTACHMENTS_DISK)
            ->fileAttachmentsVisibility(self::DESCRIPTION_ATTACHMENTS_VISIBILITY);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeFinished(Builder $query): Builder
    {
        return $query->whereNotNull('ended_at');
    }

    private static ?self $cachedActive = null;

    private static bool $activeResolved = false;

    private static ?self $cachedMostRecent = null;

    private static bool $mostRecentResolved = false;

    private static ?int $cachedFinishedCount = null;

    public static function active(): ?self
    {
        if (! static::$activeResolved) {
            static::$cachedActive = static::query()->active()->latest('id')->first();
            static::$activeResolved = true;
        }

        return static::$cachedActive;
    }

    public static function mostRecent(): ?self
    {
        if (! static::$mostRecentResolved) {
            static::$cachedMostRecent = static::query()->latest('id')->first();
            static::$mostRecentResolved = true;
        }

        return static::$cachedMostRecent;
    }

    public static function finishedCount(): int
    {
        if (static::$cachedFinishedCount === null) {
            static::$cachedFinishedCount = static::query()->finished()->count();
        }

        return static::$cachedFinishedCount;
    }

    public static function flushRequestCache(): void
    {
        static::$activeResolved = false;
        static::$cachedActive = null;
        static::$mostRecentResolved = false;
        static::$cachedMostRecent = null;
        static::$cachedFinishedCount = null;
    }

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    public function isOwnedBy(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->user_id === $userId;
    }

    public function smoker(): BelongsTo
    {
        return $this->belongsTo(Smoker::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function hasReadings(): bool
    {
        return $this->readingTimeBounds()['min'] !== null;
    }

    public function getBeganAt(): ?Carbon
    {
        $firstReadingTime = $this->readingTimeBounds()['min'];

        if ($firstReadingTime === null) {
            return null;
        }

        return Carbon::parse($firstReadingTime);
    }

    public function getDurationSeconds(): int
    {
        ['min' => $start, 'max' => $end] = $this->readingTimeBounds();

        if (! $start || ! $end) {
            return 0;
        }

        return Carbon::parse($start)->diffInSeconds($end);
    }

    public function getElapsedSeconds(): int
    {
        $start = $this->readingTimeBounds()['min'];

        if (! $start) {
            return 0;
        }

        return Carbon::parse($start)->diffInSeconds(now());
    }

    public function getDurationLabel(): string
    {
        return (string) CarbonInterval::seconds($this->getDurationSeconds())->cascade();
    }

    public function syncEndedAtFromReadings(): bool
    {
        $lastReadingTime = $this->readingTimeBounds()['max'];

        if ($lastReadingTime === null) {
            return false;
        }

        $endedAt = Carbon::parse($lastReadingTime);

        if ($this->ended_at?->equalTo($endedAt)) {
            return false;
        }

        $this->ended_at = $endedAt;
        $this->saveQuietly();

        return true;
    }

    public static function syncAllEndedAtFromReadings(bool $onlyMissing = false): int
    {
        $query = static::query()->orderBy('id');

        if ($onlyMissing) {
            $query->whereNull('ended_at');
        }

        $updated = 0;

        $query->each(function (self $cook) use (&$updated): void {
            if ($cook->syncEndedAtFromReadings()) {
                $updated++;
            }
        });

        return $updated;
    }

    public function syncStartTimeFromReadings(): void
    {
        $firstReadingTime = $this->readingTimeBounds()['min'];

        if ($firstReadingTime === null) {
            return;
        }

        $start = Carbon::parse($firstReadingTime);

        if ($this->created_at?->equalTo($start)) {
            return;
        }

        $this->created_at = $start;
        $this->saveQuietly();
    }

    /**
     * @return array{min: ?string, max: ?string}
     */
    private function readingTimeBounds(): array
    {
        if ($this->relationLoaded('readings') && $this->readings->isNotEmpty()) {
            return [
                'min' => $this->readings->min('time')?->toDateTimeString(),
                'max' => $this->readings->max('time')?->toDateTimeString(),
            ];
        }

        $bounds = $this->readings()
            ->selectRaw('MIN(time) as min_time, MAX(time) as max_time')
            ->first();

        return [
            'min' => $bounds?->min_time,
            'max' => $bounds?->max_time,
        ];
    }
}
