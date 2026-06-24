@php
    use App\Models\Cook;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    @include('partials.head')
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
<flux:header container class="border-b border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left"/>

    <x-app-logo href="{{ route('home') }}" wire:navigate/>

    <flux:navbar class="-mb-px max-lg:hidden">
        <flux:navbar.item :href="route('home')"
                          :current="request()->routeIs('home')"
                          icon="home"
                          wire:navigate
        >
            {{ __('Home') }}
        </flux:navbar.item>

        <flux:navbar.item :href="route('cooks')"
                          :current="request()->routeIs('cooks')"
                          icon="presentation-chart-line"
                          wire:navigate
                          badge="{{ Cook::finishedCount() }}"
        >
            {{ __('Cooks') }}
        </flux:navbar.item>
    </flux:navbar>

    <flux:spacer/>

    <div class="flex items-center gap-3">
        <livewire:live-cook-indicator />
        <x-desktop-user-menu/>
    </div>
</flux:header>

<!-- Mobile Menu -->
<flux:sidebar collapsible="mobile" sticky
              class="lg:hidden border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
    <flux:sidebar.header>
        <x-app-logo :sidebar="true" href="{{ route('home') }}" />
        <flux:sidebar.collapse
            class="in-data-flux-sidebar-on-desktop:not-in-data-flux-sidebar-collapsed-desktop:-mr-2"/>
    </flux:sidebar.header>

    <flux:sidebar.nav>
        <flux:sidebar.group :heading="__('Platform')">
            <flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                {{ __('Home') }}
            </flux:sidebar.item>
            <flux:sidebar.item icon="presentation-chart-line" :href="route('cooks')" :current="request()->routeIs('cooks')"
                               wire:navigate badge="{{ Cook::finishedCount() }}">
                {{ __('Cooks') }}
            </flux:sidebar.item>
        </flux:sidebar.group>
    </flux:sidebar.nav>
</flux:sidebar>

{{ $slot }}

@persist('toast')
<flux:toast.group>
    <flux:toast/>
</flux:toast.group>
@endpersist

@fluxScripts
@filamentScripts
</body>
</html>
