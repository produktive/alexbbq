@props([
    'cook',
    'editable' => false,
    'live' => false,
    'wireKey' => null,
])

@php
    $canEdit = $editable && $cook->isOwnedBy(auth()->id());
@endphp

<div @if (filled($wireKey)) wire:key="{{ $wireKey }}" @endif>
    <div
        wire:ignore
        x-data="window.cookChart(null, @js($canEdit), @js($live), @js($cook->id))"
        @class(['cook-chart-shell' => isset($menu)])
        {{ $attributes }}
    >
    @isset($menu)
        <div class="cook-chart-menu">
            <flux:dropdown position="bottom" align="end">
                <flux:button
                    variant="ghost"
                    size="sm"
                    icon="ellipsis-vertical"
                    aria-label="Cook chart options"
                />

                <flux:menu>
                    {{ $menu }}
                </flux:menu>
            </flux:dropdown>
        </div>
    @endisset

    @if ($canEdit)
        <div @class(['cook-chart-panel-header', 'pe-10' => isset($menu)])>
            <div class="flex min-w-0 flex-col items-start gap-2">
                <label class="inline-flex w-fit cursor-pointer items-center gap-2">
                    <flux:switch x-model="editMode" />
                    <flux:text size="md">Edit chart</flux:text>
                </label>

                <flux:text x-show="editMode" size="md" class="text-zinc-500 dark:text-zinc-400">
                    <span x-show="touchEditing">Tap a point or drag to select a range</span>
                    <span x-show="! touchEditing">Right-click a point or drag to select a range</span>
                </flux:text>

                <flux:text x-show="! editMode" size="md" class="text-zinc-500 dark:text-zinc-400">
                    <span x-show="touchEditing">Pinch to zoom<span x-show="isZoomed">, drag to pan</span></span>
                    <span x-show="! touchEditing">Ctrl+scroll to zoom, drag to pan</span>
                </flux:text>
            </div>
        </div>
    @endif

    <div class="cook-chart-legend-bar">
        <div class="cook-chart-legend-bar__series" role="group" aria-label="Chart series">
            <button
                type="button"
                class="cook-chart-legend-item cook-chart-legend-item--food"
                x-bind:class="{ 'is-hidden': ! legendVisible.food }"
                x-bind:aria-pressed="legendVisible.food"
                @click="toggleDataset(0)"
            >
                <span class="cook-chart-legend-swatch" aria-hidden="true"></span>
                Food
            </button>
            <button
                type="button"
                class="cook-chart-legend-item cook-chart-legend-item--bbq"
                x-bind:class="{ 'is-hidden': ! legendVisible.bbq }"
                x-bind:aria-pressed="legendVisible.bbq"
                @click="toggleDataset(1)"
            >
                <span class="cook-chart-legend-swatch" aria-hidden="true"></span>
                BBQ
            </button>
            <span x-show="hasNotedReadings" x-cloak class="cook-chart-legend-note-hint" aria-hidden="true">
                <span class="cook-chart-legend-note-hint__marker" aria-hidden="true"></span>
                Note
            </span>
        </div>

        <div
            class="cook-chart-legend-bar__zoom"
            x-show="isZoomed || (! canModify && exploreActive())"
            x-cloak
        >
            <flux:text
                x-show="! isZoomed && ! canModify"
                x-cloak
                size="md"
                class="cook-chart-legend-zoom-hint"
            >
                <span x-show="touchEditing">Pinch to zoom</span>
                <span x-show="! touchEditing">Ctrl+scroll to zoom</span>
            </flux:text>

            <flux:button
                size="sm"
                variant="ghost"
                class="shrink-0"
                x-show="isZoomed"
                x-cloak
                @click="resetZoom()"
            >
                Reset Zoom
            </flux:button>
        </div>
    </div>

    <div class="cook-chart">
        <div class="relative h-full">
            <canvas x-ref="canvas" class="block h-full w-full"></canvas>

            @if ($canEdit)
                <div
                    x-show="selection.dragging || selection.active"
                    x-bind:style="selection.overlayStyle"
                    class="cook-chart-selection-overlay"
                    x-cloak
                ></div>

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

        <div
            x-show="loading"
            x-cloak
            class="cook-chart-overlay"
        >
            <div class="flex items-center gap-2 text-sm text-neutral-500 dark:text-neutral-400">
                <flux:icon.loading variant="mini" />
                Loading chart…
            </div>
        </div>

        <div
            x-show="loadError"
            x-cloak
            class="cook-chart-overlay"
        >
            <flux:text size="sm" class="text-neutral-500 dark:text-neutral-400">
                Could not load chart data.
            </flux:text>
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
    </div>
</div>
