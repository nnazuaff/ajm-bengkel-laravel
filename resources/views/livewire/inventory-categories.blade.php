<div>
    <flux:modal name="inventory-categories" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-2xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="inventory-categories-heading">
        @if($showForm)
            <div class="space-y-5" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                <flux:heading size="lg" level="2" id="inventory-categories-heading">Kelola kategori</flux:heading>
                @if(session('inventoryCategoryStatus'))<p role="status" class="text-sm text-emerald-700 dark:text-emerald-300">{{ session('inventoryCategoryStatus') }}</p>@endif
        <form wire:submit="saveCategory" class="flex flex-wrap items-start gap-3">
            <div class="min-w-0 flex-1"><flux:input wire:model="categoryName" label="Nama kategori baru" required maxlength="120" /></div>
            <flux:button class="mt-6" type="submit" wire:loading.attr="disabled">Simpan kategori</flux:button>
        </form>
        <p class="text-sm text-zinc-500">Kategori hanya dapat dihapus jika tidak dipakai barang, termasuk barang arsip.</p>
        <flux:error name="categoryDeletion" />
        <ul class="divide-y divide-zinc-200 dark:divide-zinc-700" aria-label="Daftar kategori inventori">
            @forelse($categories as $category)
                <li wire:key="inventory-category-{{ $category->id }}" class="flex items-center justify-between gap-3 py-3">
                    <span class="min-w-0 break-words">{{ $category->name }}</span>
                    <flux:button size="sm" variant="danger" wire:click="deleteCategory({{ $category->id }})" wire:confirm="Hapus kategori ini secara permanen? Hanya kategori yang tidak dipakai barang dapat dihapus." wire:loading.attr="disabled" wire:target="deleteCategory" aria-label="Hapus kategori {{ $category->name }}">Hapus</flux:button>
                </li>
            @empty
                <li class="py-3 text-sm text-zinc-500">Belum ada kategori.</li>
            @endforelse
        </ul>
                <div class="flex justify-end"><flux:button type="button" wire:click="closeForm">Tutup</flux:button></div>
            </div>
        @endif
    </flux:modal>
</div>
