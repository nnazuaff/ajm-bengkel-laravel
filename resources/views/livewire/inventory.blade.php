<section x-data="{ editorTrigger: null, stockTrigger: null, historyTrigger: null }"
    x-on:inventory-editor-closed.window="$nextTick(() => editorTrigger?.isConnected ? editorTrigger.focus() : $refs.createInventory.focus())"
    x-on:inventory-stock-closed.window="$nextTick(() => stockTrigger?.isConnected ? stockTrigger.focus() : $refs.createInventory.focus())"
    x-on:inventory-history-closed.window="$nextTick(() => historyTrigger?.isConnected ? historyTrigger.focus() : $refs.createInventory.focus())"
    x-on:inventory-categories-closed.window="$nextTick(() => $refs.manageCategories.focus())" class="mx-auto w-full max-w-7xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl" level="1">Inventori</flux:heading><flux:text class="mt-1">Barang, batas stok, dan mutasi yang dapat ditelusuri.</flux:text></div>
        <div class="flex flex-wrap gap-3"><flux:button x-ref="manageCategories" wire:click="openCategories" wire:loading.attr="disabled">Kelola kategori</flux:button><flux:button x-ref="createInventory" x-on:click="editorTrigger = $el" variant="primary" icon="plus" wire:click="create" wire:loading.attr="disabled">Tambah barang</flux:button></div>
    </header>
    @if(session('inventoryStatus'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('inventoryStatus') }}</p>@endif
    <div class="grid items-end gap-4 md:grid-cols-3">
        <flux:input data-workshop-search wire:model.live.debounce.300ms="search" label="Cari nama atau SKU" type="search" maxlength="120" icon="magnifying-glass" />
        <flux:field><flux:label for="category-filter">Kategori</flux:label><select id="category-filter" wire:model.live="categoryFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></flux:field>
        <flux:field><flux:label for="stock-filter">Ketersediaan stok</flux:label><select id="stock-filter" wire:model.live="stockFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Semua stok</option><option value="low">Stok rendah</option><option value="out">Stok habis</option></select></flux:field>
    </div>
    <p role="status" class="text-sm text-zinc-500">{{ $items->total() }} barang <span wire:loading wire:target="search,stockFilter,categoryFilter">· Memuat…</span></p>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"><table class="workshop-table"><caption class="sr-only">Daftar inventori</caption><thead><tr><th scope="col">Barang</th><th scope="col">Kategori</th><th scope="col">Harga jual</th><th scope="col">Stok / minimum</th><th scope="col">Tindakan</th></tr></thead><tbody>
        @forelse($items as $item)<tr wire:key="inventory-{{ $item->id }}">
            <td><p class="font-medium">{{ $item->name }}</p><p class="text-xs text-zinc-500">{{ $item->sku }} · {{ $item->is_active ? 'Aktif' : 'Tidak aktif' }}</p></td>
            <td>{{ $item->category?->name ?? '-' }}</td><td class="whitespace-nowrap">{{ $this->formatMoney($item->selling_price) }}</td>
            <td><flux:badge :color="$item->current_stock === 0 ? 'red' : ($item->current_stock <= $item->minimum_stock ? 'amber' : 'green')">{{ $item->current_stock }} / {{ $item->minimum_stock }} {{ $item->unit }}</flux:badge></td>
            <td><div class="flex flex-wrap gap-2"><flux:button size="sm" x-on:click="editorTrigger = $el" wire:loading.attr="disabled" wire:click="edit({{ $item->id }})" aria-label="Edit {{ $item->name }}">Edit</flux:button><flux:button size="sm" x-on:click="stockTrigger = $el" wire:loading.attr="disabled" wire:click="openStock({{ $item->id }}, 'in')" aria-label="Restok {{ $item->name }}">Restok</flux:button><flux:button size="sm" x-on:click="stockTrigger = $el" wire:loading.attr="disabled" wire:click="openStock({{ $item->id }}, 'adjustment')" aria-label="Koreksi stok {{ $item->name }}">Koreksi</flux:button><flux:button size="sm" x-on:click="historyTrigger = $el" wire:loading.attr="disabled" wire:click="history({{ $item->id }})" aria-label="Riwayat stok {{ $item->name }}">Riwayat</flux:button><flux:button size="sm" variant="danger" wire:click="archive({{ $item->id }})" wire:confirm="Arsipkan barang? Pemakaian baru dihentikan; riwayat dan pengembalian tetap tersedia." wire:loading.attr="disabled">Arsipkan</flux:button></div></td>
        </tr>@empty<tr><td colspan="5" class="py-12 text-center text-zinc-500">Tidak ada barang yang cocok. Tambahkan barang atau ubah filter.</td></tr>@endforelse
    </tbody></table></div>{{ $items->links() }}
    <livewire:inventory-editor />
    <livewire:inventory-stock />
    <livewire:inventory-categories />
    <livewire:inventory-history />
</section>
