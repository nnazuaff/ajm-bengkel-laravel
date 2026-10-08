<div>
    <flux:modal name="inventory-editor" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-4xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="inventory-form-heading">
    @if($showForm)
        <form wire:submit="save" class="space-y-5" aria-labelledby="inventory-form-heading" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
            <flux:heading level="2" id="inventory-form-heading">{{ $editingId ? 'Edit barang' : 'Barang baru' }}</flux:heading>
            <p class="text-sm text-zinc-500">Stok awal nol. Gunakan restok atau koreksi untuk mengubah stok; setiap perubahan dicatat.</p>
            <div class="grid gap-5 md:grid-cols-3">
                <flux:input wire:model="form.sku" label="SKU / kode barang" maxlength="60" required />
                <flux:input wire:model="form.name" label="Nama barang" maxlength="120" required />
                <flux:field><flux:label for="inventory-category">Kategori</flux:label><select id="inventory-category" wire:model="form.category_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Tanpa kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select><flux:error name="form.category_id" /></flux:field>
                <flux:input wire:model="form.brand" label="Merek (opsional)" maxlength="120" />
                <flux:input wire:model="form.purchase_price" label="Harga beli (Rp)" inputmode="decimal" required />
                <flux:input wire:model="form.selling_price" label="Harga jual (Rp)" inputmode="decimal" required />
                <flux:input wire:model="form.minimum_stock" label="Stok minimum" type="number" min="0" max="2147483647" step="1" required />
                <flux:input wire:model="form.unit" label="Satuan" maxlength="30" required />
                <flux:input wire:model="form.supplier" label="Pemasok (opsional)" maxlength="120" />
                <flux:input wire:model="form.storage_location" label="Lokasi penyimpanan (opsional)" maxlength="120" />
                <flux:checkbox wire:model="form.is_active" label="Aktif untuk pemakaian dan penjualan" />
            </div>
            <div class="flex justify-end gap-3"><flux:button type="button" wire:click="closeForm">Batal</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan barang</flux:button></div>
        </form>
    @endif
    </flux:modal>
</div>
