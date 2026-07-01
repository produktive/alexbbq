<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
    <flux:sidebar sticky collapsible="mobile"
                  class="border-e border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:sidebar.header>
            <x-app-logo :sidebar="true" href="{{ route('home') }}" wire:navigate/>
            <flux:sidebar.collapse class="lg:hidden"/>
        </flux:sidebar.header>

        <flux:sidebar.nav>
            <flux:sidebar.group :heading="__('Platform')" class="grid">
                <flux:sidebar.item icon="home" :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>

                <livewire:cooks-nav-item />

                <flux:sidebar.item icon="bell-alert" :href="route('alerts')" :current="request()->routeIs('alerts')" wire:navigate>
                    {{ __('Alerts') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="fire" :href="route('smokers')" :current="request()->routeIs('smokers')"
                                   wire:navigate>
                    {{ __('Smokers') }}
                </flux:sidebar.item>

                <flux:sidebar.item icon="chart-bar" :href="route('stats')" :current="request()->routeIs('stats')" wire:navigate>
                    {{ __('Stats') }}
                </flux:sidebar.item>
            </flux:sidebar.group>
        </flux:sidebar.nav>

        <flux:sidebar.group :heading="__('Controller')" class="grid my-2">
            <livewire:cook-button/>
        </flux:sidebar.group>

        <flux:spacer/>

        <x-desktop-user-menu class="hidden lg:block" :name="auth()->user()->name"/>
    </flux:sidebar>

    <!-- Mobile User Menu -->
    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden mr-2" icon="bars-2" inset="left"/>

        <x-app-logo href="{{ route('home') }}" wire:navigate/>

        <flux:spacer/>

        <div class="flex items-center gap-3">
            <livewire:live-cook-indicator />
            <flux:dropdown position="top" align="end">
            <flux:profile
                :initials="auth()->user()->initials()"
                icon-trailing="chevron-down"
            />

            <flux:menu>
                <flux:menu.radio.group>
                    <div class="p-0 text-sm font-normal">
                        <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                            <flux:avatar
                                :name="auth()->user()->name"
                                :initials="auth()->user()->initials()"
                            />

                            <div class="grid flex-1 text-start text-sm leading-tight">
                                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
                            </div>
                        </div>
                    </div>
                </flux:menu.radio.group>

                <flux:menu.separator/>

                <flux:menu.radio.group>
                    <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                        {{ __('Settings') }}
                    </flux:menu.item>
                </flux:menu.radio.group>

                <flux:menu.separator/>

                <form method="POST" action="{{ route('logout') }}" class="w-full">
                    @csrf
                    <flux:menu.item
                        as="button"
                        type="submit"
                        icon="arrow-right-start-on-rectangle"
                        class="w-full cursor-pointer"
                        data-test="logout-button"
                    >
                        {{ __('Log out') }}
                    </flux:menu.item>
                </form>
            </flux:menu>
            </flux:dropdown>
        </div>
    </flux:header>

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
