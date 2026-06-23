<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'cook_id',
        'time',
        'probe_food',
        'probe_bbq',
        'note',
    ];

    protected $casts = [
        'time' => 'datetime',
    ];

    public function cook(): BelongsTo
    {
        return $this->belongsTo(Cook::class);
    }
}
