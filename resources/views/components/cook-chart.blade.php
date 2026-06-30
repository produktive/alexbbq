@props([
    'cook',
    'editable' => false,
    'live' => false,
    'wireKey' => null,
])

@php
    $canEdit = $editable && $cook->isOwnedBy(auth()->id());
@endphp

<div
    @if (filled($wireKey)) wire:key="{{ $wireKey }}" @endif
    wire:ignore
    x-data="window.cookChart(null, @js($canEdit), @js($live), @js($cook->id))"
    @if ($canEdit) x-ref="root" @endif
    {{ $attributes }}
>
    @if ($canEdit)
        <div class="cook-chart-panel-header">
            <div
                x-show="touchEditing"
                x-cloak
                class="flex items-end gap-2 sm:flex-row sm:items-center"
            >
                <label class="inline-flex cursor-pointer items-center gap-2">
                    <flux:text size="sm">Edit chart</flux:text>
                    <flux:switch x-model="editMode" />
                </label>

                <flux:text x-show="editMode" size="sm" class="text-zinc-500 dark:text-zinc-400">
                    Tap a point or drag to select a range
                </flux:text>
            </div>
        </div>
    @endif

    <div class="cook-chart">
        <div class="relative h-full">
            <canvas x-ref="canvas" class="block h-full w-full"></canvas>

            @if ($canEdit)
                <div
                    x-show="selection.dragging || selection.active"
                    x-bind:style="selection.overlayStyle"
                    class="pointer-events-none absolute border-x border-blue-500 bg-blue-500/15"
                    x-cloak
                ></div>
            @endif
        </div>

        <div
            x-show="loading"
            x-cloak
            class="absolute inset-0 flex items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50/90 dark:border-neutral-700 dark:bg-neutral-900/90"
        >
            <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                <flux:icon.loading variant="mini" />
                Loading chart…
            </div>
        </div>

        <div
            x-show="loadError"
            x-cloak
            class="absolute inset-0 flex items-center justify-center rounded-lg border border-neutral-200 bg-neutral-50/90 dark:border-neutral-700 dark:bg-neutral-900/90"
        >
            <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                Could not load chart data.
            </flux:text>
        </div>

        @if ($canEdit)
            <div
                x-ref="menu"
                x-show="menu.open && ! menu.useSheet"
                @click.outside="closeMenu()"
                class="absolute z-50 min-w-56 rounded border border-zinc-200 bg-white py-1 text-zinc-900 shadow dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100"
                :class="{ invisible: ! menu.positioned }"
                :style="`left:${menu.x}px;top:${menu.y}px`"
                x-cloak
            >
                @include('partials.cook-chart-point-menu')
            </div>
        @endif
    </div>

    @if ($canEdit)
        <template x-teleport="body">
            <div
                x-show="menu.open && menu.useSheet"
                x-cloak
                class="fixed inset-0 z-50 flex items-end"
                @keydown.escape.window="closeMenu()"
            >
                <button
                    type="button"
                    class="absolute inset-0 bg-black/40"
                    aria-label="Close"
                    @click="closeMenu()"
                ></button>

                <div class="relative w-full rounded-t-xl border border-zinc-200 bg-white py-2 text-zinc-900 shadow-lg dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-100">
                    @include('partials.cook-chart-point-menu')
                </div>
            </div>
        </template>
    @endif
</div>
