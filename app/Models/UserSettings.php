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
        [$foodMin, $foodMax] = self::normalizeTemperatureRange(
            $state['food'] ?? [32, 203],
            defaultMin: 32,
            defaultMax: 203,
        );

        [$bbqMin, $bbqMax] = self::normalizeTemperatureRange(
            $state['bbq'] ?? [225, 275],
            defaultMin: 225,
            defaultMax: 275,
        );

        $this->fill([
            'food_min' => $foodMin,
            'food_max' => $foodMax,
            'bbq_min' => $bbqMin,
            'bbq_max' => $bbqMax,
            'alert_interval_minutes' => (int) ($state['alert_interval_minutes'] ?? 5),
        ]);
    }

    /**
     * @param  array{0?: int|string, 1?: int|string}  $range
     * @return array{0: int, 1: int}
     */
    private static function normalizeTemperatureRange(array $range, int $defaultMin, int $defaultMax): array
    {
        $min = (int) ($range[0] ?? $defaultMin);
        $max = (int) ($range[1] ?? $defaultMax);

        if ($min > $max) {
            [$min, $max] = [$max, $min];
        }

        return [$min, $max];
    }
}
