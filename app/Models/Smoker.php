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

    public static function defaultForNewCook(): ?int
    {
        $lastUsedSmokerId = Cook::query()->latest('id')->value('smoker_id');

        if ($lastUsedSmokerId !== null) {
            return (int) $lastUsedSmokerId;
        }

        return static::query()->latest('id')->value('id');
    }
}
