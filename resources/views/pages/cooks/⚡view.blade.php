<?php

use App\Models\Cook;
use App\Models\Reading;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {

    public Cook $cook;

    public array $selectedReadingIds = [];

    public ?int $selectedReadingId = null;

    public function render()
    {
        return $this->view()->title($this->cook->title);
    }

    private function cleanTemp(?int $value): ?int
    {
        return $value === 0 ? null : $value;
    }

    #[Computed]
    public function chartData(): array
    {
        $readings = $this->cook
            ->readings()
            ->orderBy('time')
            ->get();

        if ($readings->isEmpty()) {
            return [
                'startSecondsOfDay' => 0,
                'food' => [],
                'bbq' => [],
            ];
        }

        $start = $readings->first()->time;

        return [
            // Seconds-since-midnight of the first reading's clock time, taken
            // at face value (no timezone conversion). The frontend uses this
            // plus each point's elapsed-seconds offset to rebuild wall-clock
            // labels via plain arithmetic - never through a JS Date object,
            // which would apply the browser's local timezone.
            'startSecondsOfDay' => $start->hour * 3600 + $start->minute * 60 + $start->second,

            // 'x' is a duration (seconds elapsed since the first reading),
            // not a timestamp - this is what gives the line chart correct,
            // proportional spacing between points instead of treating every
            // reading as evenly spaced.
            //
            // Using a plain timestamp subtraction here rather than
            // diffInSeconds(): Carbon's diffIn*() methods changed their
            // default sign convention between major versions (absolute in
            // Carbon 2, signed in Carbon 3), which silently flipped the
            // direction of every point after the first.
            // ->all() at the end matters here: ->values() alone still
            // returns a Collection, and Collections get wrapped by
            // Livewire's wire-protocol serializer (so they can be hydrated
            // back into Collection instances later). That wrapping is
            // invisible in @js() on first page load - it just JSON-encodes
            // whatever it's given - but it DOES show up when this same
            // array is sent as a dispatch() event payload, since dispatch
            // payloads go through Livewire's normal wire serialization.
            // Plain arrays avoid that wrapping entirely.
            'food' => $readings->map(fn ($r) => [
                'x' => $r->time->getTimestamp() - $start->getTimestamp(),
                'y' => $this->cleanTemp($r->probe_food),
                'id' => $r->id,
            ])->values()->all(),

            'bbq' => $readings->map(fn ($r) => [
                'x' => $r->time->getTimestamp() - $start->getTimestamp(),
                'y' => $this->cleanTemp($r->probe_bbq),
                'id' => $r->id,
            ])->values()->all(),
        ];
    }

    public function deletePoint(int $id): array
    {
        Reading::whereKey($id)->delete();

        return $this->chartData;
    }

    public function deleteBefore(int $id): array
    {
        $reading = Reading::findOrFail($id);

        $this->cook->readings()
            ->where('time', '<', $reading->time)
            ->delete();

        return $this->chartData;
    }

    public function deleteAfter(int $id): array
    {
        $reading = Reading::findOrFail($id);

        $this->cook->readings()
            ->where('time', '>', $reading->time)
            ->delete();

        return $this->chartData;
    }

    public function deleteSelectedPoints(array $ids): array
    {
        Reading::whereIn('id', $ids)->delete();

        $this->selectedReadingIds = [];

        return $this->chartData;
    }
}
?>
<flux:container>

    <flux:heading level="1" size="xl" class="my-2">
        {{ $this->cook->title }}
    </flux:heading>

    <flux:text size="md" class="my-2">
        Began {{ $this->cook->created_at->format('F j, Y \a\t g:i A') }}
    </flux:text>

    <flux:text size="sm" class="my-2">
        {{ CarbonInterval::seconds($this->cook->getDurationSeconds())->cascade() }}
    </flux:text>

    <div
        wire:ignore
        x-data="window.cookChart(@js($this->chartData))"
        class="relative space-y-4 h-125"
    >
        <canvas x-ref="canvas"></canvas>

        {{-- Drag-selection highlight --}}
        <div
            x-show="selection.startX !== null && selection.endX !== null"
            x-bind:style="selectionOverlayStyle()"
            class="absolute top-0 bottom-0 bg-blue-500/15 border-x border-blue-500 pointer-events-none"
            x-cloak
        ></div>

        {{-- Context menu --}}
        <div
            x-show="menu.open"
            @click.outside="menu.open = false"
            class="fixed z-50 min-w-56 bg-white border rounded shadow py-1"
            :style="`left:${menu.x}px;top:${menu.y}px`"
            x-cloak
        >
            <template x-if="!selection.active">
                <div class="flex flex-col">
                    <button type="button" class="px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="deletePoint()">
                        Delete this point
                    </button>
                    <button type="button" class="px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="deleteBefore()">
                        Delete all before this point
                    </button>
                    <button type="button" class="px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="deleteAfter()">
                        Delete all after this point
                    </button>
                </div>
            </template>

            <template x-if="selection.active">
                <div class="flex flex-col">
                    <button type="button" class="px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="deleteSelected()">
                        Delete <span x-text="selection.ids.length"></span> selected points
                    </button>
                    <button type="button" class="px-3 py-1.5 text-left text-sm hover:bg-gray-100" @click="menu.open = false; clearSelection()">
                        Clear selection
                    </button>
                </div>
            </template>
        </div>
    </div>

    <div id="cook-description" class="my-2">
        {!! $this->cook->description !!}
    </div>

    @auth
        <div class="flex justify-end">
            <flux:button href="{{ route('cooks.edit', $this->cook) }}">
                Edit Cook
            </flux:button>
        </div>
    @endauth

</flux:container>
