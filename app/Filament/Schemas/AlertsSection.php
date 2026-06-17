<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\Slider;
use Filament\Schemas\Components\Section;
use Filament\Support\Icons\Heroicon;
use Filament\Support\RawJs;

class AlertsSection
{
    public static function make($collapse = false): Section
    {
        return Section::make($collapse ? 'Alerts' : null)
            ->description($collapse ? 'Set your minimum and maximum temperature alerts for this cook.' : null)
            ->icon($collapse ? Heroicon::BellAlert : null)
            ->collapsible($collapse)
            ->collapsed()
            ->persistCollapsed()
            ->schema([

                // Food Temperature
                Slider::make('food')
                    ->label('Food Temperature')
                    ->range(minValue: 32, maxValue: 210)
                    ->default([32, 203])
                    ->step(1)
                    ->decimalPlaces(0)
                    ->tooltips(RawJs::make(<<<'JS'
                                `${$value.toFixed(0) === '32' ? 'Off' : $value.toFixed(0)+' °F'}`
                                JS
                    ))
                    ->nonLinearPoints(['20%' => 140, '80%' => 203])
                    // ->pips(PipsMode::Values)
                    // ->pipsValues([32, 140, 165, 190, 200, 210])
                    ->extraAlpineAttributes([
                        'x-init' => <<<'JS'
                                $nextTick(() => {
                                    const labels = $el.querySelectorAll('.noUi-value');
                                    labels.forEach(el => {
                                        if (el.innerText.trim() === '32') {
                                            el.innerText = 'Off';
                                        }
                                    });
                                });
                            JS
                    ])
                    ->fillTrack([false, true, false])
                    ->extraAttributes([
                        'class' => 'food-slider',
                    ]),

                // BBQ Temperature
                Slider::make('bbq')
                    ->label('BBQ Temperature')
                    ->range(minValue: 32, maxValue: 500)
                    ->default([225, 275])
                    ->step(1)
                    ->decimalPlaces(0)
                    ->tooltips(RawJs::make(<<<'JS'
                                `${$value.toFixed(0) === '32' ? 'Off' : $value.toFixed(0)+' °F'}`
                                JS
                    ))
                    ->nonLinearPoints(['20%' => 225, '80%' => 300])
                    // ->pips(PipsMode::Values)
                    // ->pipsValues([32, 225, 250, 275, 300, 325, 500])
                    ->extraAlpineAttributes([
                        'x-init' => <<<'JS'
                                $nextTick(() => {
                                    const labels = $el.querySelectorAll('.noUi-value');
                                    labels.forEach(el => {
                                        if (el.innerText.trim() === '32') {
                                            el.innerText = 'Off';
                                        }
                                    });
                                });
                            JS
                    ])
                    ->fillTrack([false, true, false])
                    ->extraAttributes([
                        'class' => 'bbq-slider',
                    ]),
            ]);
    }
}
