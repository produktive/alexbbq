@guest
    <x-layouts::app.header :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.header>
@endguest
@auth
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main>
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endauth
