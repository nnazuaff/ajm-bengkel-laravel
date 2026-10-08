<section class="mx-auto w-full max-w-5xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
        <flux:heading size="xl" level="1">Booking saya</flux:heading>
        <flux:text class="mt-1">Ajukan jadwal kedatangan. Bengkel akan mengonfirmasi permintaan Anda.</flux:text>
        </div>
        <flux:button variant="primary" icon="plus" x-on:booking-request-closed.window="$nextTick(() => $el.focus())" wire:click="createBooking" wire:loading.attr="disabled">Buat booking</flux:button>
    </header>
    @if (session('status'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    <livewire:booking-request />
    <div class="space-y-4">
        <flux:heading level="2">Permintaan Anda</flux:heading>
        @forelse ($bookings as $booking)
            <article wire:key="my-booking-{{ $booking->id }}" class="workshop-panel space-y-3">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-semibold">{{ $booking->booking_number }}</h3>
                    <flux:badge :color="$booking->status->color()">{{ $booking->status->label() }}</flux:badge>
                </header>
                <p class="text-sm">{{ $booking->license_plate }} · {{ $booking->brand }} {{ $booking->model }}</p>
                <p class="text-sm text-zinc-500">{{ $booking->booking_date->format('d/m/Y') }} · {{ substr($booking->arrival_time, 0, 5) }} WIB · {{ $booking->service_type }}</p>
                <p class="whitespace-pre-line break-words text-sm">{{ $booking->complaint }}</p>
                @if ($booking->status->customerCanCancel())
                    <flux:button size="sm" wire:click="cancel({{ $booking->id }})" wire:confirm="Batalkan permintaan booking ini?" wire:loading.attr="disabled" aria-label="Batalkan {{ $booking->booking_number }}">Batalkan booking</flux:button>
                @endif
            </article>
        @empty
            <p class="workshop-panel text-sm text-zinc-500">Belum ada booking. Klik Buat booking untuk mengajukan jadwal.</p>
        @endforelse
        {{ $bookings->links() }}
    </div>
</section>
