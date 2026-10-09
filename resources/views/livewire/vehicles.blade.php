<section x-data="{ modalTrigger: null }" x-on:vehicle-editor-closed.window="$nextTick(() => (document.getElementById(modalTrigger) ?? document.getElementById('create-vehicle')).focus())" class="mx-auto w-full max-w-7xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Kendaraan</flux:heading>
            <flux:text class="mt-1">Identitas motor, pemilik, dan odometer terakhir.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" id="create-vehicle" x-on:click="modalTrigger = $el.id" wire:click="create" wire:loading.attr="disabled">Tambah kendaraan</flux:button>
    </header>

    @if (session('status'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    @error('archive')
        <p role="alert" class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">{{ $message }}</p>
    @enderror



    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="w-full sm:max-w-md"><flux:input data-workshop-search wire:model.live.debounce.300ms="search" label="Cari kendaraan" placeholder="Pelat, motor, nama, atau telepon pemilik" icon="magnifying-glass" type="search" maxlength="120" /></div>
        <p class="text-sm text-zinc-500" role="status">{{ $vehicles->total() }} kendaraan <span wire:loading wire:target="search" class="ml-2">· Mencari…</span></p>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
        <table class="workshop-responsive-table w-full text-left text-sm" role="table">
            <caption class="sr-only">Daftar kendaraan bengkel</caption>
            <thead role="rowgroup" class="border-b border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
                <tr role="row"><th role="columnheader" scope="col" class="px-5 py-3 font-medium">Kendaraan</th><th role="columnheader" scope="col" class="px-5 py-3 font-medium">Pelanggan</th><th role="columnheader" scope="col" class="px-5 py-3 text-right font-medium">Odometer</th><th role="columnheader" scope="col" class="px-5 py-3 text-right font-medium">Tindakan</th></tr>
            </thead>
            <tbody role="rowgroup" class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($vehicles as $vehicle)
                    <tr role="row" wire:key="vehicle-{{ $vehicle->id }}">
                        <td role="cell" data-label="Kendaraan" class="px-5 py-4"><p class="font-semibold text-zinc-900 dark:text-white">{{ $vehicle->license_plate }}</p><p class="mt-1 text-zinc-500">{{ $vehicle->brand }} {{ $vehicle->model }}{{ $vehicle->year ? ' · '.$vehicle->year : '' }}</p></td>
                        <td role="cell" data-label="Pelanggan" class="px-5 py-4"><p>{{ $vehicle->customer->name }}</p><p class="mt-1 text-zinc-500">{{ $vehicle->customer->phone }}</p></td>
                        <td role="cell" data-label="Odometer" class="px-5 py-4 text-right tabular-nums">{{ number_format($vehicle->latest_mileage, 0, ',', '.') }} km</td>
                        <td role="cell" data-label="Tindakan" class="px-5 py-4 text-right">
                            <div class="flex flex-wrap justify-end gap-1">
                                <flux:button size="sm" variant="ghost" id="edit-vehicle-{{ $vehicle->id }}" x-on:click="modalTrigger = $el.id" wire:click="edit({{ $vehicle->id }})" wire:loading.attr="disabled" aria-label="Edit {{ $vehicle->license_plate }}">Edit</flux:button>
                                @can('delete', $vehicle)
                                    <flux:button size="sm" variant="ghost" wire:click="archive({{ $vehicle->id }})" wire:confirm="Arsipkan kendaraan ini? Data akan disembunyikan dari daftar aktif. Riwayat servis tetap tersimpan. Pemulihan belum tersedia di halaman ini." wire:loading.attr="disabled" wire:target="archive" aria-label="Arsipkan {{ $vehicle->license_plate }}">Arsipkan</flux:button>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr role="row"><td role="cell" colspan="4" class="px-5 py-12 text-center text-zinc-500">{{ $search !== '' ? 'Tidak ada kendaraan yang cocok. Coba pelat atau nama pemilik lain.' : 'Belum ada kendaraan. Daftarkan motor pelanggan untuk memulai pencatatan servis.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $vehicles->links() }}
    <livewire:vehicle-editor />
</section>
