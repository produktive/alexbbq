@php
    use Illuminate\Support\HtmlString;
@endphp

<x-layouts::app :title="__('Cook Statistics')">
    <flux:container class="flex flex-1 flex-col gap-6">
        <div>
            <flux:heading size="xl">{{ __('Cook Statistics') }}</flux:heading>
            <flux:text>
                Read some completely unnecessary data statistics about the cooks.
            </flux:text>
        </div>


        <div class="stats-cards">
            <x-stats-card
                :label="__('Total Cooks')"
                icon="fire"
                variant="cooks"
            >
                {{ number_format($stats->totalCooks) }}
            </x-stats-card>

            <x-stats-card
                :label="__('Total Cook Time')"
                icon="clock"
                variant="total-time"
            >
                {!! new HtmlString(nl2br(e($stats->totalDurationLabel()))) !!}
            </x-stats-card>

            <x-stats-card
                :label="__('Average Cook Time')"
                icon="chart-bar"
                variant="average-time"
            >
                {!! new HtmlString(nl2br(e($stats->averageDurationLabel()))) !!}
            </x-stats-card>

            <x-stats-card
                :label="__('Total Readings')"
                icon="signal"
                variant="readings"
            >
                {{ number_format($stats->totalReadings) }}
            </x-stats-card>
        </div>
    </flux:container>
</x-layouts::app>
