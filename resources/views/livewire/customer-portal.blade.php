<section class="mx-auto w-full max-w-6xl space-y-6">
    <header class="workshop-page-heading">
        <div><flux:heading size="xl" level="1">Portal pelanggan</flux:heading><flux:text class="mt-1">Motor, progres pekerjaan, dan bon milik Anda.</flux:text></div>
        <div class="flex flex-wrap gap-3">
            @if($pendingCheckIn)<flux:button disabled>Check-in masih menunggu</flux:button><flux:button :href="route('check-in')">Lihat check-in aktif</flux:button>
            @else<flux:button :href="route('check-in')" variant="primary">Check-in</flux:button>@endif
            <flux:button :href="route('booking.mine')" wire:navigate>Booking saya</flux:button>
        </div>
    </header>
    <p role="status" wire:loading.delay class="text-sm text-zinc-500">Memuat catatan servis…</p>
    @if (! $customer)
        <div class="workshop-panel space-y-3">
            <h2 class="text-lg font-semibold">Histori lama menunggu verifikasi identitas</h2>
            <p class="text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">Booking tetap dapat diajukan. Saat datang, petugas akan memverifikasi identitas dan kepemilikan motor sebelum membuka akses histori lama. Kesamaan nomor telepon saja tidak memberikan akses.</p>
            <p class="text-sm">Anda tetap dapat mengajukan dan memantau permintaan melalui Booking saya.</p>
        </div>
    @else
        @if($checkIns->isNotEmpty())
        <section aria-labelledby="checkins-heading" class="space-y-3">
            <h2 id="checkins-heading" class="text-lg font-semibold">Check-in saya</h2>
            @foreach($checkIns as $checkIn)
                <article class="workshop-panel space-y-2" wire:key="portal-checkin-{{ $checkIn->id }}">
                    <p class="text-sm">{{ $checkIn->checked_in_at->format('d/m/Y H:i') }} · {{ match($checkIn->status->value) { 'waiting'=>'Menunggu konfirmasi mekanik', 'processing'=>'Sedang diproses', 'converted_to_service'=>'Diterima servis', default=>'Dibatalkan' } }}</p>
                    @if($checkIn->serviceOrder && $checkIn->serviceOrder->customer_id === $customer->id)
                        <flux:button size="sm" wire:click="selectOrder({{ $checkIn->service_order_id }})" wire:loading.attr="disabled">Lihat hasil check-in · {{ $checkIn->serviceOrder->service_number }}</flux:button>
                    @endif
                </article>
            @endforeach
        </section>
        @endif
        <section aria-labelledby="vehicles-heading" class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3"><h2 id="vehicles-heading" class="text-lg font-semibold">Motor saya</h2><flux:button size="sm" wire:click="selectVehicle" wire:loading.attr="disabled">Semua motor</flux:button></div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @forelse ($vehicles as $motor)
                    <article wire:key="portal-vehicle-{{ $motor->id }}" class="workshop-panel space-y-3 {{ $selectedVehicle === $motor->id ? 'ring-2 ring-zinc-500' : '' }}">
                        <h3 class="font-semibold">{{ $motor->license_plate }}</h3>
                        <p class="text-sm">{{ $motor->brand }} {{ $motor->model }} @if ($motor->year) · {{ $motor->year }} @endif</p>
                        <p class="text-sm text-zinc-500">{{ $motor->latest_mileage }} km @if ($motor->trashed()) · Diarsipkan @endif</p>
                        <flux:button size="sm" wire:click="selectVehicle({{ $motor->id }})" wire:loading.attr="disabled" aria-label="Riwayat {{ $motor->license_plate }}">Lihat riwayat</flux:button>
                    </article>
                @empty
                    <p class="workshop-empty-state sm:col-span-2 lg:col-span-3">Belum ada motor terdaftar pada data pelanggan Anda.</p>
                @endforelse
            </div>
        </section>
        <section aria-labelledby="history-heading" class="space-y-3">
            <h2 id="history-heading" class="text-lg font-semibold">Progres & riwayat servis @if ($vehicle) · {{ $vehicle->license_plate }} @endif</h2>
            @forelse ($orders as $service)
                <article wire:key="portal-service-{{ $service->id }}" class="workshop-panel flex flex-wrap items-center justify-between gap-4">
                    <div class="min-w-0 space-y-1"><h3 class="break-words font-semibold">{{ $service->service_number }}</h3><p class="text-sm text-zinc-500">{{ $service->vehicle->license_plate }} · {{ $service->received_at->format('d/m/Y') }} · {{ $service->current_mileage }} km</p><p class="text-sm">Mekanik: {{ $service->mechanic?->name ?? 'Belum ditugaskan' }}</p></div>
                    <div class="flex flex-wrap items-center gap-3"><flux:badge :color="$service->status->color()">{{ $service->status->label() }}</flux:badge><flux:button size="sm" wire:click="selectOrder({{ $service->id }})" wire:loading.attr="disabled" aria-label="Detail {{ $service->service_number }}">Detail servis</flux:button></div>
                </article>
            @empty
                <p class="workshop-empty-state">Belum ada catatan servis untuk motor yang dipilih.</p>
            @endforelse
            {{ $orders->links() }}
        </section>
        @if ($order)
            <article class="workshop-panel space-y-6" aria-labelledby="detail-heading" wire:key="portal-detail-{{ $order->id }}">
                <header class="flex flex-wrap items-center justify-between gap-3"><h2 id="detail-heading" class="text-xl font-semibold">{{ $order->service_number }}</h2><flux:badge :color="$order->status->color()">{{ $order->status->label() }}</flux:badge></header>
                <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-sm text-zinc-500">Diterima</dt><dd>{{ $order->received_at->format('d/m/Y H:i') }}</dd></div>
                    <div><dt class="text-sm text-zinc-500">Mulai dikerjakan</dt><dd>{{ $order->started_at?->format('d/m/Y H:i') ?? 'Belum tercatat' }}</dd></div>
                    <div><dt class="text-sm text-zinc-500">Selesai</dt><dd>{{ $order->completed_at?->format('d/m/Y H:i') ?? 'Belum tercatat' }}</dd></div>
                    <div><dt class="text-sm text-zinc-500">Diserahkan</dt><dd>{{ $order->delivered_at?->format('d/m/Y H:i') ?? 'Belum tercatat' }}</dd></div>
                    <div><dt class="text-sm text-zinc-500">Kilometer</dt><dd>{{ $order->current_mileage }} km</dd></div>
                    <div><dt class="text-sm text-zinc-500">Mekanik</dt><dd>{{ $order->mechanic?->name ?? 'Belum ditugaskan' }}</dd></div>
                </dl>
                <dl class="grid gap-4 md:grid-cols-2"><div><dt class="font-semibold">Keluhan pelanggan</dt><dd class="mt-2 whitespace-pre-line break-words text-sm">{{ $order->complaint }}</dd></div><div><dt class="font-semibold">Diagnosis mekanik</dt><dd class="mt-2 whitespace-pre-line break-words text-sm">{{ $order->diagnosis ?: 'Diagnosis belum dicatat.' }}</dd></div></dl>
                <section class="space-y-3" aria-labelledby="jobs-heading"><h3 id="jobs-heading" class="font-semibold">Pekerjaan</h3>
                    @forelse ($order->jobs as $job)
                        <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"><div class="flex flex-wrap justify-between gap-2"><h4 class="font-medium">{{ $job->name }}</h4><span class="text-sm">{{ $job->status->label() }}</span></div>@if ($job->description)<p class="mt-2 whitespace-pre-line break-words text-sm">{{ $job->description }}</p>@endif<p class="mt-2 text-sm text-zinc-500">{{ $job->mechanic?->name ?? $order->mechanic?->name ?? 'Mekanik belum ditugaskan' }} · Jasa Rp {{ $job->labor_price }}</p></div>
                    @empty <p class="text-sm text-zinc-500">Pekerjaan belum dicatat.</p> @endforelse
                </section>
                <section class="space-y-3" aria-labelledby="parts-heading"><h3 id="parts-heading" class="font-semibold">Suku cadang</h3>
                    @forelse ($order->parts as $part)<div class="rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-800"><p class="break-words font-medium">{{ $part->description }}</p><p class="mt-1">{{ $part->quantity }} × Rp {{ $part->unit_price }} · Rp {{ $part->subtotal }}</p>@if ($part->returned_at)<p class="mt-1 text-zinc-500">Dikembalikan / tidak terpakai · {{ $part->returned_at->format('d/m/Y H:i') }}</p>@endif</div>
                    @empty <p class="text-sm text-zinc-500">Suku cadang belum dicatat.</p> @endforelse
                </section>
                <section class="space-y-3" aria-labelledby="photos-heading"><h3 id="photos-heading" class="font-semibold">Foto pekerjaan</h3><div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @forelse ($order->documentation as $photo)
                        <figure class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-800"><a href="{{ route('customer.documentation.show', $photo) }}" target="_blank" rel="noopener" aria-label="Buka foto {{ $photo->category->label() }}"><img src="{{ route('customer.documentation.show', $photo) }}" alt="{{ $photo->caption ?: $photo->category->label() }}" loading="lazy" class="h-48 w-full bg-zinc-100 object-contain dark:bg-zinc-800"></a><figcaption class="space-y-1 p-3 text-sm"><p class="font-medium">{{ $photo->category->label() }} · {{ $photo->created_at?->format('d/m/Y H:i') }}</p>@if ($photo->caption)<p class="break-words">{{ $photo->caption }}</p>@endif @if ($photo->serviceJob)<p class="text-zinc-500">{{ $photo->serviceJob->name }}</p>@endif</figcaption></figure>
                    @empty <p class="text-sm text-zinc-500">Foto pekerjaan belum tersedia.</p> @endforelse
                </div></section>
                <section class="border-t border-zinc-200 pt-5 dark:border-zinc-800" aria-labelledby="service-receipt-heading"><h3 id="service-receipt-heading" class="font-semibold">Bon servis</h3>
                    @if ($order->receipt)
                        <p class="mt-2">{{ $order->receipt->receipt_number }} · {{ $order->receipt->status->label() }}</p><p class="mt-1 text-lg font-semibold">Total Rp {{ $order->receipt->grand_total }}</p><p class="mt-1 text-sm">{{ $order->receipt->payment_status->label() }}</p>
                        @foreach ($order->receipt->payments as $payment)<p class="mt-2 text-sm">{{ $payment->paid_at->format('d/m/Y H:i') }} · {{ $payment->method->label() }} · Rp {{ $payment->amount }} @if ($payment->reversed_at) · Dibalik dalam pembukuan @endif</p>@endforeach
                        @if ($order->receipt->void_reason)<p class="mt-2 break-words text-sm">Alasan pembatalan: {{ $order->receipt->void_reason }}</p>@endif
                        <div class="mt-3 flex flex-wrap gap-3"><flux:button size="sm" :href="route('customer.receipts.show', $order->receipt)">Lihat bon</flux:button><flux:button size="sm" :href="route('customer.receipts.image', $order->receipt)">Unduh PNG</flux:button></div>
                    @else <p class="mt-2 text-sm text-zinc-500">Bon belum diterbitkan. Biaya pekerjaan di atas bukan total tagihan final.</p> @endif
                </section>
            </article>
        @endif
        <section aria-labelledby="receipts-heading" class="space-y-3"><h2 id="receipts-heading" class="text-lg font-semibold">Semua bon saya</h2><p class="text-sm text-zinc-500">Termasuk pembelian suku cadang tanpa servis.</p>
            @forelse ($receipts as $receipt)<article wire:key="portal-receipt-{{ $receipt->id }}" class="workshop-panel flex flex-wrap items-center justify-between gap-3"><div><h3 class="font-semibold">{{ $receipt->receipt_number }}</h3><p class="mt-1 text-sm">{{ $receipt->transaction_date->format('d/m/Y') }} · {{ $receipt->status->label() }} · {{ $receipt->payment_status->label() }}</p><p class="mt-1 font-medium">Rp {{ $receipt->grand_total }}</p></div><div class="flex flex-wrap gap-3"><flux:button size="sm" :href="route('customer.receipts.show', $receipt)" aria-label="Lihat {{ $receipt->receipt_number }}">Lihat bon</flux:button><flux:button size="sm" :href="route('customer.receipts.image', $receipt)" aria-label="Unduh PNG {{ $receipt->receipt_number }}">Unduh PNG</flux:button></div></article>
            @empty <p class="workshop-empty-state">Belum ada bon yang diterbitkan untuk akun Anda.</p> @endforelse
            {{ $receipts->links() }}
        </section>
    @endif
</section>
