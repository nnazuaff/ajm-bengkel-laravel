<section class="space-y-5 border-t border-zinc-200 pt-5 dark:border-zinc-800" aria-labelledby="parts-heading-{{ $serviceOrderId }}">
    <header class="flex flex-wrap items-center justify-between gap-3"><div><flux:heading level="2" id="parts-heading-{{ $serviceOrderId }}">Part servis</flux:heading><flux:text>Harga dan nama disimpan saat pemakaian; stok otomatis berkurang.</flux:text></div><p class="font-medium">Subtotal: {{ $partsSubtotal }}</p></header>
    @if(session('partStatus'))<p role="status" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('partStatus') }}</p>@endif
    <flux:error name="status" />
    @if($locked)<p role="status" class="text-sm text-zinc-500">Part terkunci karena servis sudah selesai/ditutup atau bon telah final. Pemakaian dan pengembalian manual tidak tersedia.</p>
    @else
    <form wire:submit="usePart" class="space-y-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
        <flux:input wire:model.live.debounce.300ms="search" label="Cari part tersedia" type="search" maxlength="120" placeholder="Nama atau SKU" />
        <div class="grid items-end gap-4 md:grid-cols-[1fr_8rem_auto]">
            <flux:field><flux:label for="part-picker-{{ $serviceOrderId }}">Part</flux:label><select id="part-picker-{{ $serviceOrderId }}" wire:model="inventoryItemId" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900" required><option value="">Pilih part</option>@foreach($matches as $item)<option value="{{ $item->id }}">{{ $item->sku }} · {{ $item->name }} · stok {{ $item->current_stock }} {{ $item->unit }} · {{ $this->formatMoney($item->selling_price) }}</option>@endforeach</select><flux:error name="inventoryItemId" /></flux:field>
            <flux:input wire:model="quantity" label="Jumlah" type="number" min="1" max="2147483647" step="1" required />
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Pakai part</flux:button>
        </div>
        <flux:error name="unit_price" />
        @if($refineSearch)<p role="status" class="text-sm text-amber-700 dark:text-amber-300">Lebih dari 30 part cocok. Perjelas pencarian.</p>@elseif($matches->isEmpty())<p role="status" class="text-sm text-zinc-500">Tidak ada part aktif dengan stok tersedia.</p>@endif
    </form>
    @endif
    <div class="overflow-x-auto"><table class="workshop-table"><caption class="sr-only">Pemakaian dan pengembalian part servis</caption><thead><tr><th scope="col">Part / petugas</th><th scope="col">Jumlah</th><th scope="col">Harga saat dipakai</th><th scope="col">Subtotal</th><th scope="col">Status / tindakan</th></tr></thead><tbody>
    @forelse($parts as $part)<tr wire:key="service-part-{{ $part->id }}"><td><p class="font-medium">{{ $part->description }}</p><p class="text-xs text-zinc-500">{{ $part->inventoryItem?->sku ?? '—' }} · {{ $part->user?->name ?? '—' }}</p></td><td>{{ $part->quantity }}</td><td class="whitespace-nowrap">{{ $this->formatMoney($part->unit_price) }}</td><td class="whitespace-nowrap">{{ $this->formatMoney($part->subtotal) }}</td><td>@if($part->returned_at)<flux:badge color="zinc">Dikembalikan</flux:badge><p class="mt-1 text-xs text-zinc-500">{{ $part->returned_at->format('d/m/Y H:i') }}</p>@elseif(!$locked)<flux:button size="sm" wire:click="returnPart({{ $part->id }})" wire:confirm="Kembalikan seluruh jumlah part ini? Stok dipulihkan; riwayat tetap tersimpan." wire:loading.attr="disabled" aria-label="Kembalikan {{ $part->description }}">Kembalikan</flux:button>@else<flux:badge color="green">Terpakai</flux:badge>@endif</td></tr>
    @empty<tr><td colspan="5" class="py-8 text-center text-zinc-500">Belum ada part terpakai.</td></tr>@endforelse
    </tbody></table></div>
</section>
