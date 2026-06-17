@php
    use App\Models\Cook;
    use Carbon\CarbonInterval;
    use Illuminate\Support\HtmlString;

    $interval = CarbonInterval::seconds(
            Cook::all()
            ->sum(fn (Cook $cook) => $cook->getDurationSeconds())
        )->cascade();

    $lines = [];

    if ($interval->years) {
        $lines[] = $interval->years . ' year' . ($interval->years > 1 ? 's' : '');
    }

    if ($interval->months) {
        $lines[] = $interval->months . ' month' . ($interval->months > 1 ? 's' : '');
    }

    if ($interval->weeks) {
        $lines[] = $interval->weeks . ' week' . ($interval->weeks > 1 ? 's' : '');
    }

    if ($interval->daysExcludeWeeks) {
        $lines[] = $interval->daysExcludeWeeks . ' day' . ($interval->daysExcludeWeeks > 1 ? 's' : '');
    }

    if ($interval->hours) {
        $lines[] = $interval->hours . ' hour' . ($interval->hours > 1 ? 's' : '');
    }

    if ($interval->minutes) {
        $lines[] = $interval->minutes . ' minute' . ($interval->minutes > 1 ? 's' : '');
    }

    if ($interval->seconds) {
        $lines[] = $interval->seconds . ' second' . ($interval->seconds > 1 ? 's' : '');
    }

    $human = implode("<br>", $lines);
@endphp
<x-layouts::app :title="__('Cook Statistics')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700 p-6">
                <flux:heading level="1" size="xl">
                    Total Cook Time
                </flux:heading>

                <flux:text>
                    {{ new HtmlString($human) }}
                </flux:text>

            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern
                    class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20"/>
            </div>
            <div
                class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
                <x-placeholder-pattern
                    class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20"/>
            </div>
        </div>
        <div
            class="relative h-full flex-1 overflow-hidden rounded-xl border border-neutral-200 dark:border-neutral-700">
            <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20"/>
        </div>
    </div>
</x-layouts::app>
