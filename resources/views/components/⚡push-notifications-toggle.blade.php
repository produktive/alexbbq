<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component {
    public bool $enabled = false;

    public function mount(): void
    {
        $this->syncEnabledState();
    }

    public function refreshPushStatus(): void
    {
        $this->syncEnabledState();
    }

    #[Computed]
    public function label(): string
    {
        return $this->enabled
            ? __('Disable Push Notifications')
            : __('Enable Push Notifications');
    }

    #[Computed]
    public function icon(): string
    {
        return $this->enabled ? 'bell-slash' : 'bell-alert';
    }

    private function syncEnabledState(): void
    {
        $this->enabled = Auth::user()->pushSubscriptions()->exists();
    }
}
?>

<div class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-col items-start gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <flux:heading size="lg">{{ __('Push Notifications') }}</flux:heading>
            <flux:text>
                {{ __('Enable browser push notifications to receive temperature alerts on this device.') }}
            </flux:text>
        </div>

        <flux:badge
            :color="$enabled ? 'green' : 'zinc'"
            size="sm"
            class="w-fit shrink-0"
        >
            {{ $enabled ? __('Enabled on this device') : __('Not enabled') }}
        </flux:badge>
    </div>

    @if ($enabled)
        <flux:button
            variant="ghost"
            :icon="$this->icon"
            x-data
            x-on:click="window.pushNotifications.disable().then(() => $wire.refreshPushStatus())"
        >
            {{ $this->label }}
        </flux:button>
    @else
        <flux:button
            :icon="$this->icon"
            x-data
            x-on:click="window.pushNotifications.enable().then(() => $wire.refreshPushStatus())"
        >
            {{ $this->label }}
        </flux:button>
    @endif
</div>
