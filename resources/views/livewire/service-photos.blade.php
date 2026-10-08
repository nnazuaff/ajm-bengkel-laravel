<section class="space-y-5 border-t border-zinc-200 pt-6 dark:border-zinc-800" aria-labelledby="photos-heading-{{ $serviceOrderId }}">
    <header>
        <flux:heading level="3" id="photos-heading-{{ $serviceOrderId }}">Dokumentasi servis</flux:heading>
        <p class="mt-1 text-sm text-zinc-500">Foto privat; admin, mekanik ditugaskan, dan akun pelanggan pemilik dapat melihat.</p>
    </header>
    @if ($notice)<p role="status" class="text-sm text-emerald-700 dark:text-emerald-300">{{ $notice }}</p>@endif
    <flux:error name="photo" />
    @if ($readOnly)
        <p role="status" class="text-sm text-zinc-500">Order sudah ditutup. Dokumentasi hanya dapat dilihat.</p>
    @else
        <form wire:submit="savePhoto" class="space-y-4 rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
            <flux:input type="file" wire:model="photo" label="Foto dokumentasi" accept="image/jpeg,image/png,image/webp" required />
            <p class="text-sm text-zinc-500">JPG, PNG, WebP · Maksimal 5 MB · Maksimal 4096 × 4096 piksel.</p>
            @if ($previewUrl)
                <figure class="space-y-2">
                    <img src="{{ $previewUrl }}" alt="Pratinjau foto" class="h-40 w-full rounded-lg object-contain" />
                    <figcaption class="text-sm text-zinc-500">Pratinjau foto · belum disimpan</figcaption>
                </figure>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label for="photo-category-{{ $serviceOrderId }}">Kategori</flux:label>
                    <select id="photo-category-{{ $serviceOrderId }}" wire:model="category" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($categories as $categoryOption)<option value="{{ $categoryOption->value }}">{{ $categoryOption->label() }}</option>@endforeach
                    </select>
                    <flux:error name="category" />
                </flux:field>
                <flux:field>
                    <flux:label for="photo-job-{{ $serviceOrderId }}">Pekerjaan (opsional)</flux:label>
                    <select id="photo-job-{{ $serviceOrderId }}" wire:model="serviceJobId" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">Dokumentasi order</option>
                        @foreach ($jobs as $job)<option value="{{ $job->id }}">{{ $job->name }}</option>@endforeach
                    </select>
                    <flux:error name="service_job_id" />
                </flux:field>
            </div>
            <flux:textarea wire:model="caption" label="Keterangan (opsional)" rows="2" maxlength="1000" />
            <div class="flex flex-wrap items-center justify-end gap-3">
                <span role="status" wire:loading wire:target="photo,savePhoto" class="text-sm text-zinc-500">Mengunggah foto…</span>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="photo,savePhoto">Simpan foto</flux:button>
            </div>
        </form>
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($photos as $photoRecord)
            <figure wire:key="service-photo-{{ $photoRecord->id }}" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                <a href="{{ route('documentation.show', $photoRecord) }}" target="_blank" rel="noopener" aria-label="Buka foto {{ $photoRecord->category->label() }} {{ $photoRecord->caption }}">
                    <img src="{{ route('documentation.show', $photoRecord) }}" alt="{{ $photoRecord->caption ?? 'Dokumentasi '.$photoRecord->category->label() }}" loading="lazy" class="h-48 w-full bg-zinc-100 object-contain dark:bg-zinc-800" />
                </a>
                <figcaption class="space-y-2 p-3 text-sm">
                    <p class="font-medium">{{ $photoRecord->category->label() }} · {{ $photoRecord->serviceJob?->name ?? 'Dokumentasi order' }}</p>
                    @if ($photoRecord->caption)<p class="whitespace-pre-line break-words">{{ $photoRecord->caption }}</p>@endif
                    <p class="text-xs text-zinc-500">{{ $photoRecord->uploader?->name ?? 'Petugas' }} · {{ $photoRecord->created_at?->format('d/m/Y H:i') }}</p>
                    @if (! $readOnly)
                        @if ($pendingRemovalId === $photoRecord->id)
                            <div class="space-y-3 rounded-lg bg-amber-50 p-3 dark:bg-amber-950" role="alert">
                                <p class="font-medium">Hapus foto dari galeri?</p>
                                <p>Bukti asli tetap tersimpan secara privat. Tindakan dicatat dalam audit.</p>
                                <div class="flex flex-wrap gap-2">
                                    <flux:button type="button" size="sm" wire:click="cancelRemoval">Batal</flux:button>
                                    <flux:button type="button" size="sm" variant="danger" wire:click="remove" wire:loading.attr="disabled" wire:target="remove">Ya, hapus dari galeri</flux:button>
                                </div>
                            </div>
                        @else
                            <flux:button type="button" size="sm" variant="ghost" wire:click="requestRemoval({{ $photoRecord->id }})" aria-label="Hapus foto {{ $photoRecord->category->label() }} {{ $photoRecord->id }}" wire:loading.attr="disabled">Hapus dari galeri</flux:button>
                        @endif
                    @endif
                </figcaption>
            </figure>
        @empty
            <p class="col-span-full rounded-lg border border-dashed border-zinc-300 px-4 py-8 text-center text-sm text-zinc-500 dark:border-zinc-700">Belum ada foto dokumentasi.</p>
        @endforelse
    </div>
</section>
