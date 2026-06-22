<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    public $timestamps = false;

    protected $casts = [
        'time' => 'datetime',
    ];

    public function cook(): BelongsTo
    {
        return $this->belongsTo(Cook::class);
    }
}
