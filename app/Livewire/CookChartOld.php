<?php

namespace App\Livewire;

use App\Models\Cook;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Filament\Widgets\ChartWidget;

class CookChartOld extends ChartWidget
{
    public Cook $cook;

    protected ?string $maxHeight = '500px';

    protected string $color = 'primary';

    public function getHeading(): ?string
    {
        return Carbon::parse($this->cook->created_at)->format('F j, Y \a\t g:i A');
    }

    public function getDescription(): ?string
    {
        return 'Duration: '.CarbonInterval::seconds($this->cook->getDurationSeconds())->cascade();
    }

    protected function getData(): array
    {
        $readings = $this->cook->readings();

        switch ($this->filter) {
            case '15m':
                $readings = $readings->where('time', '>=', Carbon::parse($readings->max('time'))->subMinutes(15));
                break;

            case 'hour':
                $readings = $readings->where('time', '>=', Carbon::parse($readings->max('time'))->subHour());
                break;

            case '3h':
                $readings = $readings->where('time', '>=', Carbon::parse($readings->max('time'))->subHours(3));
                break;

            default:
        }

        $readings = $readings->orderBy('time')->get();

        return [
            'datasets' => [
                [
                    'label' => 'Food',
                    'data' => $readings->map(fn ($r) => [
                        'x' => Carbon::parse($r->time)->timestamp,
                        'y' => $this->cleanTemp($r->probe_food),
                        'id' => $r->id,
                    ]),
                ],
                [
                    'label' => 'BBQ',
                    'data' => $readings->map(fn ($r) => [
                        'x' => Carbon::parse($r->time)->timestamp,
                        'y' => $this->cleanTemp($r->probe_bbq),
                        'id' => $r->id,
                    ]),
                ],
            ],

            'labels' => $readings
                ->pluck('time')
                ->map(fn ($time) => Carbon::parse($time)->format('g:i A'))
                ->all(),

            'line' => [
                'tension' => .4,
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    private function cleanTemp(?int $value): ?int
    {
        return $value === 0 ? null : $value;
    }

    protected function getFilters(): ?array
    {
        return [
            'all' => 'Entire duration',
            '15m' => 'Last 15 minutes',
            'hour' => 'Last hour',
            '3h' => 'Last 3 hours',
        ];
    }
}
