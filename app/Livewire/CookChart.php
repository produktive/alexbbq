<?php

namespace App\Livewire;

use App\Models\Cook;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\ChartWidget\Concerns\HasFiltersSchema;

class CookChart extends ChartWidget
{
//    use HasFiltersSchema;

    public Cook $cook;
    protected ?string $maxHeight = '500px';

    public function getHeading(): ?string
    {
        return $this->cook->title;
    }

    public function getDescription(): ?string
    {
        return strip_tags(html_entity_decode($this->cook->description));
    }

    protected function getData(): array
    {

        $activeFilter = $this->filter;

        $readings = $this->cook
            ->readings()
            ->orderBy('time')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Food',
                    'data' => $readings->pluck('probe_food')
                        ->map(fn ($value) => $value === 0 ? null : $value)
                        ->all(),
                ],
                [
                    'label' => 'BBQ',
                    'data' => $readings->pluck('probe_bbq')
                        ->map(fn ($value) => $value === 0 ? null : $value)
                        ->all(),
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

//    protected function getFilters(): ?array
//    {
//        return [
//            'hour' => 'Last hour',
//            'month' => 'Last month',
//            'year' => 'This year',
//        ];
//    }
}
