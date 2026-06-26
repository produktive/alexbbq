@php
    use Carbon\CarbonInterval;
    use Illuminate\Support\Facades\DB;
    use Illuminate\Support\HtmlString;

    $totalSeconds = (int) DB::query()
        ->fromSub(
            DB::table('readings')
                ->selectRaw('cook_id, CAST((strftime(\'%s\', MAX(time)) - strftime(\'%s\', MIN(time))) AS INTEGER) as cook_seconds')
                ->groupBy('cook_id'),
            'durations',
        )
        ->sum('cook_seconds');

    $human = (string) CarbonInterval::seconds($totalSeconds)->cascade();
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
                    {{ new HtmlString(nl2br(e($human))) }}
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
