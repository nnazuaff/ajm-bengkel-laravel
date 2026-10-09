<section class="mx-auto w-full max-w-7xl space-y-6">
    <header>
        <flux:heading size="xl" level="1">Booking</flux:heading>
        <flux:text class="mt-1">Tinjau permintaan, konfirmasi kedatangan, lalu terima sebagai servis.</flux:text>
    </header>
    @if (session('status'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    <livewire:booking-review />
    <div class="grid gap-4 md:grid-cols-3">
        <flux:input data-workshop-search wire:model.live.debounce.300ms="search" label="Cari booking" type="search" maxlength="120" placeholder="Nomor, pelat, nama, atau telepon" icon="magnifying-glass" />
        <flux:field>
            <flux:label for="booking-status-filter">Status</flux:label>
            <select id="booking-status-filter" wire:model.live="statusFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
            </select>
        </flux:field>
        <flux:input wire:model.live="dateFilter" label="Tanggal kedatangan" type="date" />
    </div>
    <p role="status" class="text-sm text-zinc-500">{{ $bookings->total() }} booking <span wire:loading wire:target="search,statusFilter,dateFilter">· Memuat…</span></p>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="workshop-responsive-table workshop-table" role="table">
            <caption class="sr-only">Antrean booking bengkel</caption>
            <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Booking / jadwal</th><th role="columnheader" scope="col">Motor</th><th role="columnheader" scope="col">Pemesan</th><th role="columnheader" scope="col">Status</th><th role="columnheader" scope="col">Tindakan</th></tr></thead>
            <tbody role="rowgroup">
                @forelse ($bookings as $booking)
                    <tr role="row" wire:key="booking-{{ $booking->id }}">
                        <td role="cell" data-label="Booking / jadwal"><p class="font-medium">{{ $booking->booking_number }}</p><p class="mt-1 whitespace-nowrap text-xs text-zinc-500">{{ $booking->booking_date->format('d/m/Y') }} · {{ substr($booking->arrival_time, 0, 5) }} WIB</p></td>
                        <td role="cell" data-label="Motor"><p class="font-semibold">{{ $booking->license_plate }}</p><p class="mt-1 text-zinc-500">{{ $booking->brand }} {{ $booking->model }}</p></td>
                        <td role="cell" data-label="Pemesan"><p>{{ $booking->name }}</p><p class="mt-1 text-zinc-500">{{ $booking->phone }}</p></td>
                        <td role="cell" data-label="Status" class="whitespace-nowrap"><flux:badge :color="$booking->status->color()" size="sm">{{ $booking->status->label() }}</flux:badge></td>
                        <td role="cell" data-label="Tindakan"><flux:button size="sm" variant="ghost" wire:click="openBooking({{ $booking->id }})" x-on:booking-review-closed.window="if ($event.detail.id === {{ $booking->id }}) $nextTick(() => $el.focus())" wire:loading.attr="disabled" aria-label="Tinjau {{ $booking->booking_number }}">Tinjau</flux:button></td>
                    </tr>
                @empty
                    <tr role="row"><td role="cell" colspan="5" class="py-12 text-center text-zinc-500">{{ $search !== '' || $statusFilter !== '' || $dateFilter !== '' ? 'Tidak ada booking yang cocok. Ubah pencarian atau filter.' : 'Belum ada permintaan booking.' }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $bookings->links() }}
</section>
