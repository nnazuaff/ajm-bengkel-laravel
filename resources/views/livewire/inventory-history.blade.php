<div>
    <flux:modal :closable="false" name="inventory-history" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-5xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="stock-history-heading">
    @if($historyItem)
        <section class="space-y-4" aria-labelledby="stock-history-heading" x-init="$nextTick(() => $el.querySelector('button')?.focus())">
            <header class="flex items-center justify-between gap-3"><flux:heading level="2" id="stock-history-heading">Riwayat · {{ $historyItem->name }}</flux:heading><flux:button size="sm" wire:click="closeHistory">Tutup riwayat</flux:button></header>
            <div class="overflow-x-auto"><table class="workshop-table"><caption class="sr-only">Mutasi stok {{ $historyItem->sku }}</caption><thead><tr><th>Waktu / petugas</th><th>Jenis</th><th>Selisih</th><th>Sebelum / sesudah</th><th>Alasan / referensi</th></tr></thead><tbody>@forelse($movements as $movement)<tr wire:key="movement-{{ $movement->id }}"><td>{{ $movement->created_at->format('d/m/Y H:i') }}<p class="text-xs text-zinc-500">{{ $movement->creator?->name ?? '-' }}</p></td><td>{{ $movement->type }}</td><td>{{ $movement->quantity > 0 ? '+' : '' }}{{ $movement->quantity }}</td><td>{{ $movement->stock_before }} / {{ $movement->stock_after }}</td><td>{{ $movement->reason }}<p class="break-all text-xs text-zinc-500">{{ $movement->reference_type }} {{ $movement->reference_id }}</p></td></tr>@empty<tr><td colspan="5" class="text-center text-zinc-500">Belum ada mutasi.</td></tr>@endforelse</tbody></table></div>{{ $movements->links() }}
        </section>
    @endif
    </flux:modal>
</div>
