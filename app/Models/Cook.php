<?php

namespace App\Models;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cook extends Model
{
    protected $fillable = [
        'smoker_id',
        'title',
        'description',
    ];
//    protected $casts = [
//        'created_at' => 'datetime',
//    ];

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function smoker(): BelongsTo
    {
        return $this->belongsTo(Smoker::class);
    }

    public function getBeganAt(): Carbon
    {
        $firstReadingTime = $this->readings()->min('time');

        if ($firstReadingTime !== null) {
            return Carbon::parse($firstReadingTime);
        }

        return Carbon::parse($this->created_at);
    }

    public function getDurationSeconds(): int
    {
        $start = $this->readings()->min('time');
        $end = $this->readings()->max('time');

        if (! $start || ! $end) {
            return 0;
        }

        return Carbon::parse($start)->diffInSeconds($end);
    }

    public function getDurationLabel(): string
    {
        return (string) CarbonInterval::seconds($this->getDurationSeconds())->cascade();
    }

    public function syncStartTimeFromReadings(): void
    {
        $firstReadingTime = $this->readings()->min('time');

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
}
