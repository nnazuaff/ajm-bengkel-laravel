<flux:dropdown position="bottom" align="start" {{ $attributes }}>
    <flux:sidebar.profile
        :name="auth()->user()->name"
        :initials="auth()->user()->initials()"
        icon:trailing="chevrons-up-down"
        :aria-label="__('Menu akun')"
        data-test="sidebar-menu-button"
    />

    <flux:menu>
        <div class="flex min-w-0 items-center gap-3 px-2 py-2 text-start text-sm">
            <flux:avatar :name="auth()->user()->name" :initials="auth()->user()->initials()" size="sm" />
            <div class="grid min-w-0 flex-1 gap-0.5">
                <flux:heading class="truncate">{{ auth()->user()->name }}</flux:heading>
                <flux:text class="truncate">{{ auth()->user()->email }}</flux:text>
            </div>
        </div>
        <flux:menu.separator />
        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
            {{ __('Pengaturan akun') }}
        </flux:menu.item>
        <flux:menu.separator />
        <form method="POST" action="{{ route('logout') }}" class="w-full">
            @csrf
            <flux:menu.item
                as="button"
                type="submit"
                icon="arrow-right-start-on-rectangle"
                class="w-full cursor-pointer"
                data-test="logout-button"
            >
                {{ __('Keluar') }}
            </flux:menu.item>
        </form>
    </flux:menu>
</flux:dropdown>
