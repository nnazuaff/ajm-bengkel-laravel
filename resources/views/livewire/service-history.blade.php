<section x-data="{ historyTrigger: null }" x-on:service-history-closed.window="$nextTick(() => historyTrigger?.isConnected ? historyTrigger.focus() : $el.querySelector('[data-workshop-search]')?.focus())" class="mx-auto w-full max-w-7xl space-y-6">
    <header><flux:heading size="xl" level="1">Riwayat servis</flux:heading><flux:text class="mt-1">Cari pelat nomor untuk melihat catatan motor, termasuk data yang sudah diarsipkan.</flux:text></header>
    <flux:input data-workshop-search wire:model.live.debounce.300ms="search" type="search" label="Cari motor / pelanggan" placeholder="Pelat, nama, atau telepon" maxlength="120" />
    <div class="workshop-panel overflow-x-auto">
        <table role="table" class="workshop-table workshop-responsive-table"><caption class="sr-only">Kendaraan dan pemilik</caption><thead role="rowgroup"><tr role="row"><th scope="col" role="columnheader">Motor</th><th scope="col" role="columnheader">Pemilik</th><th scope="col" role="columnheader">Tindakan</th></tr></thead><tbody role="rowgroup">
        @forelse ($vehicles as $vehicle)
            <tr role="row" wire:key="history-vehicle-{{ $vehicle->id }}"><td role="cell" data-label="Motor">{{ $vehicle->license_plate }} · {{ $vehicle->brand }} {{ $vehicle->model }} @if($vehicle->trashed())<flux:badge size="sm">Arsip</flux:badge>@endif</td><td role="cell" data-label="Pemilik">{{ $vehicle->customer->name }} · {{ $vehicle->customer->phone }}</td><td role="cell" data-label="Tindakan"><flux:button size="sm" wire:click="selectVehicle({{ $vehicle->id }})" x-on:click="historyTrigger = $el" wire:loading.attr="disabled" aria-label="Riwayat {{ $vehicle->license_plate }}">Riwayat</flux:button></td></tr>
        @empty
            <tr role="row"><td role="cell" colspan="3" class="py-8 text-center">Tidak ada kendaraan yang cocok.</td></tr>
        @endforelse
        </tbody></table>
    </div>
    {{ $vehicles->links() }}
    <livewire:service-history-detail />
</section>
