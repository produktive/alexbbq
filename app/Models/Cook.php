<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cook extends Model
{
    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class);
    }

    public function smoker(): BelongsTo
    {
        return $this->belongsTo(Smoker::class);
    }

    public function endedAt(): ?Carbon
    {
        return $this->readings()
            ->latest()
            ->value('created_at');
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
}
