<section class="mx-auto w-full max-w-7xl space-y-6" x-data="{ trigger: null }"
    x-on:check-in-intake-closed.window="$nextTick(() => trigger?.isConnected && trigger.focus())" wire:poll.15s>
    <header>
        <flux:heading size="xl" level="1">Customer check-in</flux:heading>
        <flux:text class="mt-1">Kontak masuk dari QR. Pilih motor dan catat keluhan untuk menerima servis.</flux:text>
    </header>
    <x-validation-summary />
    <div class="workshop-panel grid gap-5 md:grid-cols-[1fr_auto]">
        <div class="space-y-3">
            <h2 class="font-semibold">Kode bengkel aktif</h2>
            @if ($activeCode?->active && (!$activeCode->expires_at || $activeCode->expires_at->isFuture()))
                <p class="text-3xl font-semibold tracking-widest" data-check-in-code>{{ $activeCode->code }}</p>
                <p class="text-sm text-zinc-500">Berlaku sampai {{ $activeCode->expires_at?->format('d/m/Y H:i') }}.
                Berikan hanya kepada pelanggan yang hadir.</p>@else<p>Belum ada kode aktif.</p>
            @endif
            <div class="flex flex-wrap gap-3">
                <flux:button wire:click="generateCode"
                    wire:confirm="Buat kode baru? Kode sebelumnya langsung tidak berlaku." wire:loading.attr="disabled">
                    Generate kode baru</flux:button>
                <flux:button wire:click="disableCode" wire:confirm="Nonaktifkan kode check-in?"
                    wire:loading.attr="disabled">Nonaktifkan kode</flux:button>
            </div>
            <a href="{{ route('check-in') }}" target="_blank" rel="noopener"
                class="block break-all text-sm underline">{{ route('check-in') }}</a>
            <p class="text-sm text-zinc-500">QR tetap; kode dapat diganti. URL harus dapat diakses HP pelanggan, bukan
                localhost.</p>
        </div>
        <div class="space-y-2"><img src="{{ route('check-ins.qr') }}" width="240" height="240"
                alt="QR halaman check-in bengkel" class="max-w-full rounded-lg bg-white p-2" /><a
                href="{{ route('check-ins.qr') }}" download="check-in.svg" class="text-sm underline">Unduh QR</a></div>
    </div>
    <flux:select wire:model.live="status" label="Status antrean">
        <option value="waiting">Menunggu</option>
        <option value="processing">Diproses</option>
        <option value="converted_to_service">Sudah diterima servis</option>
        <option value="cancelled">Dibatalkan</option>
        <option value="">Semua</option>
    </flux:select>
    <div class="workshop-panel">
        <table role="table" class="workshop-table workshop-responsive-table">
            <caption class="sr-only">Antrean check-in pelanggan</caption>
            <thead role="rowgroup">
                <tr role="row">
                    <th scope="col">Pelanggan</th>
                    <th scope="col">Waktu</th>
                    <th scope="col">Status</th>
                    <th scope="col">Tindakan</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                @forelse($checkIns as $entry)
                    <tr role="row" wire:key="check-in-{{ $entry->id }}">
                        <td role="cell" data-label="Pelanggan">{{ $entry->customer->name }}<p class="text-sm">
                                {{ $entry->customer->phone }}</p>
                        </td>
                        <td role="cell" data-label="Waktu">{{ $entry->checked_in_at->format('d/m/Y H:i') }}</td>
                        <td role="cell" data-label="Status">{{ $entry->status->label() }}</td>
                        <td role="cell" data-label="Tindakan">
                            @if (in_array($entry->status->value, ['waiting', 'processing'], true))
                                <div class="flex flex-wrap gap-2">
                                    <flux:button size="sm" wire:click="openIntake({{ $entry->id }})"
                                        x-on:click="trigger=$el">Terima servis</flux:button>
                                    <flux:button size="sm" wire:click="cancel({{ $entry->id }})"
                                        wire:confirm="Batalkan check-in ini?">Batalkan</flux:button>
                                </div>
                            @elseif($entry->service_order_id)
                                <flux:button size="sm"
                                    href="{{ route('services.detail', $entry->service_order_id) }}" wire:navigate>Lihat
                                    servis</flux:button>
                            @endif
                        </td>
                </tr>@empty<tr role="row">
                        <td role="cell" colspan="4">Belum ada check-in pada status ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>{{ $checkIns->links() }}<livewire:check-in-intake />
</section>
