<div>
    <flux:modal :closable="false" name="inventory-stock" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-2xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="stock-form-heading">
    @if($stockItem)
        <form wire:submit="saveStock" class="space-y-5" aria-labelledby="stock-form-heading" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
            <flux:heading level="2" id="stock-form-heading">{{ ($stockForm['type'] ?? '') === 'in' ? 'Restok' : 'Koreksi stok' }} · {{ $stockItem->name }}</flux:heading>
            <p class="text-sm text-zinc-500">Stok saat ini: {{ $stockItem->current_stock }} {{ $stockItem->unit }}. {{ ($stockForm['type'] ?? '') === 'in' ? 'Masukkan jumlah tambahan positif.' : 'Masukkan selisih bertanda: positif menambah, negatif mengurangi. Bukan stok akhir.' }}</p>
            <div class="grid gap-5 md:grid-cols-2"><flux:input wire:model="stockForm.quantity" label="Selisih jumlah" type="number" step="1" required /><flux:input wire:model="stockForm.reason" label="Alasan mutasi" maxlength="255" required /></div>
            <flux:error name="stockForm.type" />
            <div class="flex justify-end gap-3"><flux:button type="button" wire:click="closeStock">Batal</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Catat mutasi</flux:button></div>
        </form>
    @endif
    </flux:modal>
</div>
