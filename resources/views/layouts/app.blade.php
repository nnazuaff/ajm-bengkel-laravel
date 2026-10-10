@if (auth()->user()?->role === \App\Enums\Role::Customer)
    <x-layouts::customer :title="$title ?? null">{{ $slot }}</x-layouts::customer>
@else
    <x-layouts::app.sidebar :title="$title ?? null">
        <flux:main id="main-content" tabindex="-1">
            {{ $slot }}
        </flux:main>
    </x-layouts::app.sidebar>
@endif
