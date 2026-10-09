<section class="mx-auto w-full max-w-7xl space-y-6">
    @if (session('status'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

        <article class="workshop-panel space-y-5 scroll-mt-6" wire:key="service-detail-{{ $selectedOrder->id }}" aria-labelledby="detail-heading">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <flux:heading size="xl" level="1" id="detail-heading">{{ $selectedOrder->service_number }}</flux:heading>
                    <p class="mt-1 text-sm text-zinc-500">{{ $selectedOrder->vehicle->license_plate }} · {{ $selectedOrder->vehicle->brand }} {{ $selectedOrder->vehicle->model }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <flux:badge :color="$selectedOrder->status->color()" size="sm">{{ $selectedOrder->status->label() }}</flux:badge>
                    <flux:button size="sm" :href="route('services.index')" wire:navigate icon="arrow-left">Daftar servis</flux:button>
                </div>
            </header>
            <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                <div><dt class="text-zinc-500">Pelanggan</dt><dd class="mt-1 font-medium">{{ $selectedOrder->customer->name }}</dd><dd class="mt-1">{{ $selectedOrder->customer->phone }}</dd></div>
                <div><dt class="text-zinc-500">Kilometer saat diterima</dt><dd class="mt-1 font-medium">{{ number_format($selectedOrder->current_mileage, 0, ',', '.') }} km</dd></div>
                <div><dt class="text-zinc-500">Sumber / petugas penerima</dt><dd class="mt-1">{{ $selectedOrder->source->label() }} · {{ $selectedOrder->receiver?->name ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Mekanik</dt><dd class="mt-1">{{ $selectedOrder->mechanic?->name ?? 'Belum ditugaskan' }}</dd></div>
                <div><dt class="text-zinc-500">Diterima</dt><dd class="mt-1">{{ $selectedOrder->received_at->format('d/m/Y H:i') }}</dd></div>
                <div><dt class="text-zinc-500">Mulai dikerjakan</dt><dd class="mt-1">{{ $selectedOrder->started_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Selesai</dt><dd class="mt-1">{{ $selectedOrder->completed_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
                <div><dt class="text-zinc-500">Diserahkan</dt><dd class="mt-1">{{ $selectedOrder->delivered_at?->format('d/m/Y H:i') ?? '—' }}</dd></div>
            </dl>
            <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                <h3 class="text-sm font-medium">Keluhan pelanggan</h3>
                <p class="mt-2 whitespace-pre-line break-words text-sm">{{ $selectedOrder->complaint }}</p>
            </div>
            <flux:error name="detail.status" />
            @if (in_array($selectedOrder->status, [\App\Enums\ServiceStatus::Delivered, \App\Enums\ServiceStatus::Cancelled], true))
                <p role="status" class="text-sm text-zinc-500">Order sudah ditutup. Diagnosis, catatan, dan status tidak dapat diubah.</p>
                <dl class="space-y-4 text-sm">
                    <div><dt class="font-medium">Diagnosis mekanik</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $selectedOrder->diagnosis ?? 'Belum dicatat.' }}</dd></div>
                    <div><dt class="font-medium">Catatan servis</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $selectedOrder->notes ?? 'Tidak ada catatan.' }}</dd></div>
                </dl>
            @else
                <form wire:submit="saveOrder" class="space-y-5">
                    <div class="grid gap-5 md:grid-cols-2">
                        <flux:field>
                            <flux:label for="detail-status">Status servis</flux:label>
                            <select id="detail-status" wire:model="detail.status" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                @foreach ($allowedStatuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                            </select>
                            <flux:description>Hanya tahap berikutnya yang tersedia. Diagnosis wajib sebelum disetujui atau selesai.</flux:description>
                        </flux:field>
                        @if ($managesWorkshop)
                            <flux:field>
                                <flux:label for="detail-mechanic">Mekanik</flux:label>
                                <select id="detail-mechanic" wire:model="detail.mechanic_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    <option value="">Belum ditugaskan</option>
                                    @if ($selectedOrder->mechanic && ! $mechanics->contains('id', $selectedOrder->mechanic_id))<option value="{{ $selectedOrder->mechanic_id }}">{{ $selectedOrder->mechanic->name }} (tidak aktif; pilih ulang)</option>@endif
                                    @foreach ($mechanics as $mechanic)<option value="{{ $mechanic->id }}">{{ $mechanic->name }}</option>@endforeach
                                </select>
                                <flux:error name="detail.mechanic_id" />
                            </flux:field>
                        @else
                            <p class="self-center text-sm text-zinc-500">Persetujuan, pembatalan, penugasan, dan penyerahan dikelola admin.</p>
                            <flux:error name="detail.mechanic_id" />
                        @endif
                        <div class="md:col-span-2"><flux:textarea wire:model="detail.diagnosis" label="Diagnosis mekanik" rows="3" maxlength="5000" placeholder="Hasil pemeriksaan; terpisah dari keluhan pelanggan." /></div>
                        <div class="md:col-span-2"><flux:textarea wire:model="detail.notes" label="Catatan servis / alasan pembatalan" rows="2" maxlength="5000" /></div>
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <span role="status" wire:loading wire:target="saveOrder" class="text-sm text-zinc-500">Menyimpan perubahan…</span>
                        <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="saveOrder">Simpan perubahan</flux:button>
                    </div>
                </form>
            @endif
            @can('manage-workshop')
                @if($selectedOrder->receipt || in_array($selectedOrder->status->value,['completed','ready_for_pickup','delivered'],true))
                <flux:button :href="route('receipts.index', ['service_order_id' => $selectedOrder->id])" wire:navigate>Bon servis</flux:button>
                @else<p class="text-sm text-zinc-500">Bon dapat dibuat setelah servis selesai.</p>@endif
            @endcan
            <livewire:service-parts :service-order-id="$selectedOrder->id" :key="'parts-'.$selectedOrder->id.'-'.$selectedOrder->status->value" />
            <livewire:service-photos :service-order-id="$selectedOrder->id" :key="'photos-'.$selectedOrder->id.'-'.$selectedOrder->status->value" />
            <livewire:service-jobs :service-order-id="$selectedOrder->id" :key="'jobs-'.$selectedOrder->id.'-'.$selectedOrder->status->value" />
        </article>
</section>
