<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettings extends Model
{
    public const TEMPERATURE_OFF = 32;

    public const ALERT_INTERVALS = [1, 3, 5, 10, 15];

    protected $fillable = [
        'user_id',
        'food_min',
        'food_max',
        'bbq_min',
        'bbq_max',
        'alert_interval_minutes',
        'last_food_alert_at',
        'last_bbq_alert_at',
    ];

    protected function casts(): array
    {
        return [
            'last_food_alert_at' => 'datetime',
            'last_bbq_alert_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUser(User $user): self
    {
        return static::query()->firstOrCreate(
            ['user_id' => $user->id],
            [
                'food_min' => 32,
                'food_max' => 203,
                'bbq_min' => 225,
                'bbq_max' => 275,
                'alert_interval_minutes' => 5,
            ],
        );
    }

    /**
     * @return array{food: array{0: int, 1: int}, bbq: array{0: int, 1: int}, alert_interval_minutes: int}
     */
    public function toAlertFormState(): array
    {
        return [
            'food' => [$this->food_min, $this->food_max],
            'bbq' => [$this->bbq_min, $this->bbq_max],
            'alert_interval_minutes' => $this->alert_interval_minutes,
        ];
    }

    public function fillFromAlertFormState(array $state): void
    {
        $food = $state['food'] ?? [32, 203];
        $bbq = $state['bbq'] ?? [225, 275];

        $this->fill([
            'food_min' => (int) ($food[0] ?? 32),
            'food_max' => (int) ($food[1] ?? 203),
            'bbq_min' => (int) ($bbq[0] ?? 225),
            'bbq_max' => (int) ($bbq[1] ?? 275),
            'alert_interval_minutes' => (int) ($state['alert_interval_minutes'] ?? 5),
        ]);
    }
}
