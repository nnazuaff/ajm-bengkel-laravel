<section class="mx-auto max-w-7xl space-y-6">
    <header><flux:heading size="xl" level="1">Riwayat servis</flux:heading><flux:text class="mt-1">Cari pelat nomor untuk melihat catatan motor, termasuk data yang sudah diarsipkan.</flux:text></header>
    <flux:input data-workshop-search wire:model.live.debounce.300ms="search" type="search" label="Cari motor / pelanggan" placeholder="Pelat, nama, atau telepon" maxlength="120" />
    <div class="workshop-panel overflow-x-auto">
        <table class="workshop-table"><caption class="sr-only">Kendaraan dan pemilik</caption><thead><tr><th>Motor</th><th>Pemilik</th><th>Tindakan</th></tr></thead><tbody>
        @forelse ($vehicles as $vehicle)
            <tr wire:key="history-vehicle-{{ $vehicle->id }}"><td>{{ $vehicle->license_plate }} · {{ $vehicle->brand }} {{ $vehicle->model }} @if($vehicle->trashed())<flux:badge size="sm">Arsip</flux:badge>@endif</td><td>{{ $vehicle->customer->name }} · {{ $vehicle->customer->phone }}</td><td><flux:button size="sm" wire:click="selectVehicle({{ $vehicle->id }})" aria-label="Riwayat {{ $vehicle->license_plate }}">Riwayat</flux:button></td></tr>
        @empty
            <tr><td colspan="3" class="py-8 text-center">Tidak ada kendaraan yang cocok.</td></tr>
        @endforelse
        </tbody></table>
    </div>
    {{ $vehicles->links() }}
    @if ($selectedVehicle)
        <flux:heading level="2">{{ $selectedVehicle->license_plate }} · {{ $selectedVehicle->customer->name }}</flux:heading>
        @forelse ($history as $order)
            <article class="workshop-panel space-y-4" wire:key="history-order-{{ $order->id }}">
                <header class="flex flex-wrap justify-between gap-2"><div><h3 class="font-semibold">{{ $order->service_number }}</h3><p class="text-sm text-zinc-500">{{ $order->received_at->format('d/m/Y H:i') }} · {{ number_format($order->current_mileage, 0, ',', '.') }} km · {{ $order->mechanic?->name ?? 'Belum ditugaskan' }}</p></div><flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge></header>
                <dl class="grid gap-4 md:grid-cols-2"><div><dt class="text-sm font-medium">Keluhan pelanggan</dt><dd class="mt-1 whitespace-pre-line break-words text-sm">{{ $order->complaint }}</dd></div><div><dt class="text-sm font-medium">Diagnosis mekanik</dt><dd class="mt-1 whitespace-pre-line break-words text-sm">{{ $order->diagnosis ?? 'Belum dicatat.' }}</dd></div></dl>
                <div><h4 class="text-sm font-medium">Pekerjaan</h4><ul class="mt-2 space-y-1 text-sm">@forelse($order->jobs as $job)<li>{{ $job->name }} · {{ $job->status->label() }} · Rp {{ str_replace('.', ',', $job->labor_price) }}@if($job->description)<p class="text-zinc-500">{{ $job->description }}</p>@endif</li>@empty<li class="text-zinc-500">Belum ada pekerjaan.</li>@endforelse</ul></div>
                <div><h4 class="text-sm font-medium">Spare part</h4><ul class="mt-2 space-y-1 text-sm">@forelse($order->parts as $part)<li>{{ $part->description }} · {{ $part->quantity }} × Rp {{ str_replace('.', ',', $part->unit_price) }} @if($part->returned_at)<span class="text-zinc-500">(dikembalikan)</span>@endif</li>@empty<li class="text-zinc-500">Tidak ada spare part tercatat.</li>@endforelse</ul></div>
                <div><h4 class="text-sm font-medium">Dokumentasi</h4><div class="mt-2 grid grid-cols-2 gap-3 md:grid-cols-4">@forelse($order->documentation as $photo)<a href="{{ route('documentation.show', $photo) }}" target="_blank" rel="noopener"><img src="{{ route('documentation.show', $photo) }}" alt="{{ $photo->caption ?: 'Dokumentasi servis' }}" loading="lazy" class="h-32 w-full rounded-lg object-contain"><p class="mt-1 text-xs">{{ $photo->category->label() }} · {{ $photo->caption }}</p></a>@empty<p class="text-sm text-zinc-500">Belum ada foto.</p>@endforelse</div></div>
                @if($order->receipt)
                    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-800"><p class="text-sm">{{ $order->receipt->receipt_number }} · Rp {{ str_replace('.', ',', $order->receipt->grand_total) }} · {{ $order->receipt->payment_status->value }}</p>@if($order->receipt->status === \App\Enums\ReceiptStatus::Draft)<flux:button size="sm" :href="route('receipts.edit', $order->receipt->id)" wire:navigate>Edit draf bon</flux:button>@else<flux:button size="sm" :href="route('receipts.show', $order->receipt)" target="_blank">Lihat bon</flux:button>@endif</div>
                @else<p class="text-sm text-zinc-500">Bon belum dibuat.</p>@endif
            </article>
        @empty
            <p class="workshop-empty-state">Belum ada servis untuk motor ini.</p>
        @endforelse
        {{ $history->links() }}
    @endif
</section>
