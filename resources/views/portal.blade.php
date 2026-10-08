<x-layouts::app :title="'Akun pelanggan'">
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <flux:heading size="xl" level="1">Akun pelanggan</flux:heading>
            <flux:text class="mt-2">Selamat datang, {{ auth()->user()->name }}.</flux:text>
        </div>
        <div class="rounded-xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:heading level="2">Akun Anda sudah siap</flux:heading>
            <flux:text class="mt-2">Ajukan jadwal servis melalui Booking saya. Petugas bengkel akan mengonfirmasi jadwal kedatangan. Riwayat servis online belum tersedia.</flux:text>
            @can('customer-portal')<flux:button variant="primary" class="mt-4" :href="route('booking.mine')" wire:navigate>Booking saya</flux:button>@endcan
            <flux:button class="mt-4" :href="route('profile.edit')" wire:navigate>Pengaturan akun</flux:button>
        </div>
    </div>
</x-layouts::app>
