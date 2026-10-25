<section x-data="{ editorTrigger: null, stockTrigger: null, historyTrigger: null }"
    x-on:inventory-editor-closed.window="$nextTick(() => editorTrigger?.isConnected ? editorTrigger.focus() : $refs.createInventory.focus())"
    x-on:inventory-stock-closed.window="$nextTick(() => stockTrigger?.isConnected ? stockTrigger.focus() : $refs.createInventory.focus())"
    x-on:inventory-history-closed.window="$nextTick(() => historyTrigger?.isConnected ? historyTrigger.focus() : $refs.createInventory.focus())"
    x-on:inventory-categories-closed.window="$nextTick(() => $refs.manageCategories.focus())" class="mx-auto w-full max-w-7xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl" level="1">Inventori</flux:heading><flux:text class="mt-1">Barang, batas stok, dan mutasi yang dapat ditelusuri.</flux:text></div>
        <div class="flex flex-wrap gap-3"><flux:button href="{{ route('receipts.create') }}" wire:navigate>Jual sparepart</flux:button><flux:button x-ref="manageCategories" wire:click="openCategories" wire:loading.attr="disabled">Kelola kategori</flux:button><flux:button x-ref="createInventory" x-on:click="editorTrigger = $el" variant="primary" icon="plus" wire:click="create" wire:loading.attr="disabled">Tambah barang</flux:button></div>
    </header>
    @if(session('inventoryStatus'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('inventoryStatus') }}</p>@endif
    <div class="flex flex-wrap items-start gap-3">
        <details data-inventory-import @if($importErrors) open @endif class="w-full rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900 sm:w-auto sm:open:w-96">
            <summary class="w-fit cursor-pointer rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-500 dark:border-zinc-700 dark:hover:bg-zinc-800">Impor</summary>
            <div class="mt-4 space-y-3">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">Unduh template, isi produk, lalu unggah CSV atau XLSX (maksimal 500 produk, 2 MB). SKU yang sudah ada ditolak; jika ada kesalahan, seluruh impor dibatalkan. Stok awal tercatat sebagai mutasi.</p>
                <div class="flex flex-wrap gap-3 text-sm font-medium">
                    <a class="underline underline-offset-4" href="{{ route('inventory.export', ['format' => 'csv', 'template' => 1]) }}">Unduh template CSV</a>
                    <a class="underline underline-offset-4" href="{{ route('inventory.export', ['format' => 'xlsx', 'template' => 1]) }}">Unduh template XLSX</a>
                </div>
                <form class="flex flex-wrap items-end gap-3" action="{{ route('inventory.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="w-full">
                        <label for="inventory-file" class="block cursor-pointer rounded-xl border-2 border-dashed border-zinc-400 bg-zinc-50 p-4 text-sm transition-colors hover:border-zinc-600 hover:bg-zinc-100 focus-within:outline-2 focus-within:outline-offset-2 focus-within:outline-zinc-500 dark:border-zinc-500 dark:bg-zinc-800 dark:hover:border-zinc-300 dark:hover:bg-zinc-700">
                            <span class="block font-semibold">Pilih file CSV atau XLSX</span>
                            <span class="mt-1 block text-xs text-zinc-600 dark:text-zinc-300">Klik kotak ini untuk memilih berkas.</span>
                            <input class="mt-3 block w-full min-w-0 cursor-pointer text-sm text-zinc-700 file:mr-3 file:cursor-pointer file:rounded-lg file:border file:border-zinc-300 file:bg-white file:px-3 file:py-2 file:font-medium dark:text-zinc-200 dark:file:border-zinc-600 dark:file:bg-zinc-900 dark:file:text-zinc-100" id="inventory-file" name="file" type="file" accept=".csv,.xlsx" required aria-describedby="inventory-file-help" />
                        </label>
                        <p id="inventory-file-help" class="mt-2 text-xs text-zinc-500">Maksimal 2 MB. Harga angka tanpa Rp; kolom Aktif: Ya atau Tidak. Kolom Stok menjadi stok awal produk baru. Periksa stok sebelum impor.</p>
                    </div>
                    <flux:button type="submit" variant="primary">Impor produk</flux:button>
                </form>
                @if($importErrors)
                    <div role="alert" class="rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200">
                        <p class="font-semibold">Impor dibatalkan. Perbaiki semua kesalahan berikut:</p>
                        <ul class="mt-2 list-inside list-disc space-y-1">@foreach($importErrors as $message)<li>{{ $message }}</li>@endforeach</ul>
                    </div>
                @endif
            </div>
        </details>
        <details data-inventory-export class="w-full rounded-xl border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-900 sm:w-auto">
            <summary class="w-fit cursor-pointer rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium hover:bg-zinc-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-500 dark:border-zinc-700 dark:hover:bg-zinc-800">Ekspor</summary>
            <div class="mt-4 flex flex-wrap gap-3 text-sm font-medium">
                <a class="underline underline-offset-4" href="{{ route('inventory.export', ['format' => 'csv']) }}">Unduh CSV</a>
                <a class="underline underline-offset-4" href="{{ route('inventory.export', ['format' => 'xlsx']) }}">Unduh XLSX</a>
            </div>
        </details>
    </div>
    <div class="grid items-end gap-4 md:grid-cols-3">
        <flux:input data-workshop-search wire:model.live.debounce.300ms="search" label="Cari nama atau SKU" type="search" maxlength="120" icon="magnifying-glass" />
        <flux:field><flux:label for="category-filter">Kategori</flux:label><select id="category-filter" wire:model.live="categoryFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</select></flux:field>
        <flux:field><flux:label for="stock-filter">Ketersediaan stok</flux:label><select id="stock-filter" wire:model.live="stockFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 dark:border-zinc-700 dark:bg-zinc-900"><option value="">Semua stok</option><option value="low">Stok rendah</option><option value="out">Stok habis</option></select></flux:field>
    </div>
    <p role="status" class="text-sm text-zinc-500">{{ $items->total() }} barang <span wire:loading wire:target="search,stockFilter,categoryFilter">· Memuat…</span></p>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"><table class="workshop-responsive-table workshop-table" role="table"><caption class="sr-only">Daftar inventori</caption><thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Barang</th><th role="columnheader" scope="col">Kategori</th><th role="columnheader" scope="col">Harga jual</th><th role="columnheader" scope="col">Stok / minimum</th><th role="columnheader" scope="col">Status</th><th role="columnheader" scope="col">Tindakan</th></tr></thead><tbody role="rowgroup">
        @forelse($items as $item)<tr role="row" wire:key="inventory-{{ $item->id }}">
            <td role="cell" data-label="Barang"><p class="font-medium">{{ $item->name }}</p><p class="text-xs text-zinc-500">{{ $item->sku }}</p></td>
            <td role="cell" data-label="Kategori">{{ $item->category?->name ?? '-' }}</td><td role="cell" data-label="Harga jual" class="whitespace-nowrap">{{ $this->formatMoney($item->selling_price) }}</td>
            <td role="cell" data-label="Stok / minimum"><flux:badge :color="$item->current_stock === 0 ? 'red' : ($item->current_stock <= $item->minimum_stock ? 'amber' : 'green')">{{ $item->current_stock }} / {{ $item->minimum_stock }} {{ $item->unit }}</flux:badge></td>
            <td role="cell" data-label="Status">
                <button type="button" role="switch" aria-checked="{{ $item->is_active ? 'true' : 'false' }}" aria-label="Status aktif {{ $item->name }}" wire:click="setActive({{ $item->id }}, {{ $item->is_active ? 'false' : 'true' }})" wire:loading.attr="disabled" wire:target="setActive" class="inline-flex min-h-11 min-w-11 items-center gap-2 rounded-lg px-1 text-sm disabled:cursor-wait disabled:opacity-50">
                    <span aria-hidden="true" class="inline-flex h-6 w-11 shrink-0 items-center rounded-full p-0.5 {{ $item->is_active ? 'bg-emerald-600' : 'bg-zinc-400 dark:bg-zinc-600' }}"><span class="size-5 rounded-full bg-white shadow-sm {{ $item->is_active ? 'translate-x-5' : '' }}"></span></span>
                    <span>{{ $item->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                </button>
            </td>
            <td role="cell" data-label="Tindakan"><div class="flex flex-wrap gap-2"><flux:button size="sm" x-on:click="editorTrigger = $el" wire:loading.attr="disabled" wire:click="edit({{ $item->id }})" aria-label="Edit {{ $item->name }}">Edit</flux:button><flux:button size="sm" x-on:click="stockTrigger = $el" wire:loading.attr="disabled" wire:click="openStock({{ $item->id }}, 'in')" aria-label="Restok {{ $item->name }}">Restok</flux:button><flux:button size="sm" x-on:click="stockTrigger = $el" wire:loading.attr="disabled" wire:click="openStock({{ $item->id }}, 'adjustment')" aria-label="Koreksi stok {{ $item->name }}">Koreksi</flux:button><flux:button size="sm" x-on:click="historyTrigger = $el" wire:loading.attr="disabled" wire:click="history({{ $item->id }})" aria-label="Riwayat stok {{ $item->name }}">Riwayat</flux:button><flux:button size="sm" variant="danger" wire:click="archive({{ $item->id }})" wire:confirm="Arsipkan barang? Pemakaian baru dihentikan; riwayat dan pengembalian tetap tersedia." wire:loading.attr="disabled">Arsipkan</flux:button></div></td>
        </tr>@empty<tr role="row"><td role="cell" colspan="6" class="py-12 text-center text-zinc-500">Tidak ada barang yang cocok. Tambahkan barang atau ubah filter.</td></tr>@endforelse
    </tbody></table></div>{{ $items->links() }}
    <livewire:inventory-editor />
    <livewire:inventory-stock />
    <livewire:inventory-categories />
    <livewire:inventory-history />
</section>
