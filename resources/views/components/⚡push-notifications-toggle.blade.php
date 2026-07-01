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

    public function clearPushSubscriptions(): void
    {
        Auth::user()->pushSubscriptions()->delete();
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

<div
    class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
    x-data="{
        working: false,
        async togglePush() {
            if (this.working) {
                return;
            }

            if (! window.pushNotifications) {
                window.alert('Push notifications are unavailable. Please refresh the page and try again.');
                return;
            }

            this.working = true;

            try {
                if ($wire.enabled) {
                    try {
                        await window.pushNotifications.disable();
                    } finally {
                        await $wire.clearPushSubscriptions();
                    }
                } else {
                    await window.pushNotifications.enable();
                    await $wire.refreshPushStatus();
                }
            } finally {
                this.working = false;
            }
        },
    }"
>
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

    <flux:button
        :variant="$enabled ? 'ghost' : 'primary'"
        :icon="$this->icon"
        x-bind:disabled="working"
        @click="togglePush()"
    >
        {{ $this->label }}
    </flux:button>
</div>
