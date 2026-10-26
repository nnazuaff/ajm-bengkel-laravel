<x-layouts::auth title="Lupa password">
    <div class="flex flex-col gap-6">
        <x-auth-header title="Lupa password" description="Fitur ini masih dalam pengembangan." />

        <p class="text-center text-sm text-zinc-600 dark:text-zinc-400">
            Reset password belum tersedia untuk sementara.
        </p>

        <flux:button :href="route('login')" wire:navigate variant="primary" class="w-full">
            Kembali ke login
        </flux:button>
    </div>
</x-layouts::auth>
