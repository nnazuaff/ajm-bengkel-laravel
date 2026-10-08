<section x-data="{ modalTrigger: null }" x-on:customer-editor-closed.window="$nextTick(() => (document.getElementById(modalTrigger) ?? document.getElementById('create-customer')).focus())" x-on:customer-account-closed.window="$nextTick(() => (document.getElementById(modalTrigger) ?? document.getElementById('create-customer')).focus())" class="mx-auto w-full max-w-7xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Pelanggan</flux:heading>
            <flux:text class="mt-1">Kontak pelanggan dan kendaraan yang terdaftar di bengkel.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" id="create-customer" x-on:click="modalTrigger = $el.id" wire:click="create" wire:loading.attr="disabled">Tambah pelanggan</flux:button>
    </header>

    @if (session('status'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    @error('archive')
        <p role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">{{ $message }}</p>
    @enderror



    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="w-full sm:max-w-md"><flux:input data-workshop-search wire:model.live.debounce.300ms="search" label="Cari pelanggan" placeholder="Nama, telepon, atau pelat nomor" icon="magnifying-glass" type="search" maxlength="120" /></div>
        <p class="text-sm text-zinc-500" role="status">{{ $customers->total() }} pelanggan <span wire:loading wire:target="search" class="ml-2">· Mencari…</span></p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Daftar pelanggan bengkel</caption>
            <thead class="border-b border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                <tr><th scope="col" class="px-5 py-3 font-medium">Pelanggan</th><th scope="col" class="px-5 py-3 font-medium">Kontak</th><th scope="col" class="px-5 py-3 text-right font-medium">Kendaraan</th><th scope="col" class="px-5 py-3 text-right font-medium">Tindakan</th></tr>
            </thead>
            <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($customers as $customer)
                    <tr wire:key="customer-{{ $customer->id }}">
                        <td class="px-5 py-4 font-medium text-zinc-900 dark:text-white">{{ $customer->name }}</td>
                        <td class="px-5 py-4"><p>{{ $customer->phone }}</p>@if ($customer->email)<p class="mt-1 text-zinc-500">{{ $customer->email }}</p>@endif</td>
                        <td class="px-5 py-4 text-right tabular-nums">{{ $customer->vehicles_count }} motor</td>
                        <td class="px-5 py-4 text-right">
                            <div class="flex justify-end gap-1">
                                <flux:button size="sm" variant="ghost" id="edit-customer-{{ $customer->id }}" x-on:click="modalTrigger = $el.id" wire:click="edit({{ $customer->id }})" wire:loading.attr="disabled" aria-label="Edit {{ $customer->name }}">Edit</flux:button><flux:button size="sm" variant="ghost" id="account-customer-{{ $customer->id }}" x-on:click="modalTrigger = $el.id" wire:click="openAccount({{ $customer->id }})" wire:loading.attr="disabled" aria-label="Akses akun {{ $customer->name }}">Akses akun</flux:button>
                                @can('delete', $customer)
                                    <flux:button size="sm" variant="ghost" wire:click="archive({{ $customer->id }})" wire:confirm="Arsipkan pelanggan ini? Data akan disembunyikan dari daftar aktif. Riwayat servis tetap tersimpan. Pemulihan belum tersedia di halaman ini." wire:loading.attr="disabled" wire:target="archive" aria-label="Arsipkan {{ $customer->name }}">Arsipkan</flux:button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-12 text-center text-zinc-500">{{ $search !== '' ? 'Tidak ada pelanggan yang cocok. Coba nama, telepon, atau pelat lain.' : 'Belum ada pelanggan. Tambahkan pelanggan pertama untuk mulai mencatat kendaraan.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
    <livewire:customer-editor />
    <livewire:customer-account />
</section>
