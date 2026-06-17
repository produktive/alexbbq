<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Smoker extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
    ];

    public function cooks(): HasMany
    {
        return $this->hasMany(Cook::class);
    }
}
