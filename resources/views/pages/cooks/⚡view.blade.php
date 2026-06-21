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
            'food' => $readings->map(fn ($r) => [
                'x' => $r->time->getTimestamp() - $start->getTimestamp(),
                'y' => $this->cleanTemp($r->probe_food),
                'id' => $r->id,
            ])->values(),

            'bbq' => $readings->map(fn ($r) => [
                'x' => $r->time->getTimestamp() - $start->getTimestamp(),
                'y' => $this->cleanTemp($r->probe_bbq),
                'id' => $r->id,
            ])->values(),
        ];
    }

    public function deletePoint(int $id): void
    {
        Reading::whereKey($id)->delete();

        $this->dispatch('chart-refresh');
    }

    public function deleteBefore(int $id): void
    {
        $reading = Reading::findOrFail($id);

        $this->cook->readings()
            ->where('time', '<', $reading->time)
            ->delete();

        $this->dispatch('chart-refresh');
    }

    public function deleteAfter(int $id): void
    {
        $reading = Reading::findOrFail($id);

        $this->cook->readings()
            ->where('time', '>', $reading->time)
            ->delete();

        $this->dispatch('chart-refresh');
    }

    public function deleteSelectedPoints(array $ids): void
    {
        Reading::whereIn('id', $ids)->delete();

        $this->selectedReadingIds = [];

        $this->dispatch('chart-refresh');
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
        x-data="window.cookChart(@js($this->chartData))"
        class="relative space-y-4 h-125"
    >
        <canvas x-ref="canvas"></canvas>

        {{-- Context menu --}}
        <div
            x-show="menu.open"
            @click.outside="menu.open = false"
            class="fixed z-50 bg-white border rounded shadow"
            :style="`left:${menu.x}px;top:${menu.y}px`"
        >
            <template x-if="!selection.active">
                <div>
                    <button @click="deletePoint()">Delete this point</button>
                    <button @click="deleteBefore()">Delete before</button>
                    <button @click="deleteAfter()">Delete after</button>
                </div>
            </template>

            <template x-if="selection.active">
                <button @click="deleteSelected()">
                    Delete selected points
                </button>
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
