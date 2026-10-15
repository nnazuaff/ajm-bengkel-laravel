<div>
    <flux:modal :closable="false" name="inventory-stock" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-2xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="stock-form-heading">
    @if($stockItem)
        <form wire:submit="saveStock" class="space-y-5" aria-labelledby="stock-form-heading" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
            <flux:heading level="2" id="stock-form-heading">{{ ($stockForm['type'] ?? '') === 'in' ? 'Restok' : 'Koreksi stok' }} · {{ $stockItem->name }}</flux:heading>
            <p class="text-sm text-zinc-500">Stok saat ini: {{ $openingStock ?? $stockItem->current_stock }} {{ $stockItem->unit }}. {{ ($stockForm['type'] ?? '') === 'in' ? 'Masukkan jumlah tambahan positif.' : 'Atur stok akhir sesuai hasil hitung fisik. Selisihnya dicatat sebagai mutasi.' }}</p>
            <div class="grid gap-5 md:grid-cols-2">
                @if(($stockForm['type'] ?? '') === 'adjustment')
                    <div>
                        <label for="stock-final" class="mb-2 block text-sm font-medium">Stok akhir ({{ $stockItem->unit }})</label>
                        <div class="flex items-center gap-2">
                            <flux:button type="button" wire:click="decreaseStock" aria-label="Kurangi stok akhir satu" wire:loading.attr="disabled">−</flux:button>
                            <input id="stock-final" name="stock-final" wire:model="stockForm.quantity" type="number" min="0" max="{{ \App\Actions\StockLedger::MAX_STOCK }}" step="1" required aria-describedby="stock-final-help" class="h-10 w-full min-w-0 rounded-lg border border-zinc-300 bg-white px-3 text-center text-base dark:border-zinc-700 dark:bg-zinc-900" />
                            <flux:button type="button" wire:click="increaseStock" aria-label="Tambah stok akhir satu" wire:loading.attr="disabled">+</flux:button>
                        </div>
                        <p id="stock-final-help" class="mt-1 text-xs text-zinc-500">Gunakan tombol atau ketik jumlah stok akhir.</p>
                        <flux:error name="stockForm.quantity" />
                    </div>
                @else
                    <flux:input wire:model="stockForm.quantity" label="Jumlah tambahan" type="number" min="1" step="1" required />
                @endif
                <flux:input wire:model="stockForm.reason" label="Alasan mutasi" maxlength="255" required />
            </div>
            <flux:error name="stockForm.type" />
            <div class="flex justify-end gap-3"><flux:button type="button" wire:click="closeStock">Batal</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Catat mutasi</flux:button></div>
        </form>
    @endif
    </flux:modal>
</div>
