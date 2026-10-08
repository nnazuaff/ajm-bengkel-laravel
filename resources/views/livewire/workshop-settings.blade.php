<section class="mx-auto w-full max-w-3xl space-y-6">
    <header><flux:heading size="xl" level="1">Identitas bengkel</flux:heading><flux:text class="mt-1">Identitas untuk bon baru. Bon final mempertahankan salinan identitas saat transaksi.</flux:text></header>
    @if (session('status'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>@endif
    <form wire:submit="save" class="space-y-5 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:input wire:model="name" label="Nama bengkel" required maxlength="120" />
        <flux:input wire:model="phone" label="Telepon / WhatsApp (opsional)" type="tel" maxlength="30" />
        <flux:textarea wire:model="address" label="Alamat (opsional)" rows="3" maxlength="2000" />
        <flux:textarea wire:model="receipt_footer" label="Pesan penutup bon (opsional)" rows="2" maxlength="1000" />
        <div class="space-y-3">
            <label for="workshop-logo" class="block text-sm font-medium">Logo bengkel (opsional)</label>
            @if($logoUrl)<img src="{{ $logoUrl }}" alt="Logo bengkel saat ini" class="h-30 w-30 rounded-lg border border-zinc-200 bg-white p-2 object-contain" />@endif
            @if($previewUrl)<img src="{{ $previewUrl }}" alt="Pratinjau logo baru" class="h-30 w-30 rounded-lg border border-zinc-200 bg-white p-2 object-contain" />@endif
            <input id="workshop-logo" type="file" wire:model="logo" accept="image/jpeg,image/png,image/webp" aria-describedby="logo-help" class="block w-full rounded-lg border border-zinc-300 p-2 text-sm focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600" />
            <p id="logo-help" class="text-sm text-zinc-500">JPEG, PNG, atau WebP. Maksimal 2 MB dan 1024 × 1024 piksel. Logo bersifat publik; jangan unggah foto pelanggan. Logo lama tetap tersedia untuk bon final.</p>
            @error('logo')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            <span role="status" wire:loading wire:target="logo" class="text-sm text-zinc-500">Mengunggah logo…</span>
        </div>
        <div class="space-y-3">
            <label for="workshop-horizontal-logo" class="block text-sm font-medium">Logo horizontal untuk navigasi (opsional)</label>
            @if($horizontalLogoUrl)<img src="{{ $horizontalLogoUrl }}" alt="Logo horizontal saat ini" width="1600" height="560" class="h-auto w-64 max-w-full rounded-lg border border-zinc-200 object-contain" />@endif
            @if($horizontalPreviewUrl)<img src="{{ $horizontalPreviewUrl }}" alt="Pratinjau logo horizontal baru" width="1600" height="560" class="h-auto w-64 max-w-full rounded-lg border border-zinc-200 object-contain" />@endif
            <input id="workshop-horizontal-logo" type="file" wire:model="horizontalLogo" accept="image/jpeg,image/png,image/webp" aria-describedby="horizontal-logo-help" class="block w-full rounded-lg border border-zinc-300 p-2 text-sm focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600" />
            <p id="horizontal-logo-help" class="text-sm text-zinc-500">Ukuran tepat 1600 × 560 piksel. JPEG, PNG, atau WebP; maksimal 2 MB. Digunakan di navbar dan sidebar, terpisah dari logo bon. Jika belum tersedia, tampil AJM Bengkel. Logo bersifat publik.</p>
            @error('horizontalLogo')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            <span role="status" wire:loading wire:target="horizontalLogo" class="text-sm text-zinc-500">Mengunggah logo horizontal…</span>
        </div>
        <div class="space-y-3">
            <label for="workshop-favicon" class="block text-sm font-medium">Favicon / ikon tab browser (opsional)</label>
            @if($faviconUrl)<img src="{{ $faviconUrl }}" alt="Favicon saat ini" width="64" height="64" class="size-16 rounded-lg border border-zinc-200 object-contain" />@endif
            @if($faviconPreviewUrl)<img src="{{ $faviconPreviewUrl }}" alt="Pratinjau favicon baru" width="64" height="64" class="size-16 rounded-lg border border-zinc-200 object-contain" />@endif
            <input id="workshop-favicon" type="file" wire:model="favicon" accept="image/png" aria-describedby="favicon-help" class="block w-full rounded-lg border border-zinc-300 p-2 text-sm focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600" />
            <p id="favicon-help" class="text-sm text-zinc-500">PNG persegi 32–512 piksel, disarankan 512 × 512. Maksimal 1 MB. Dipakai di halaman publik, login, dan admin; tanpa gambar memakai ikon bawaan. Favicon bersifat publik.</p>
            @error('favicon')<p role="alert" class="text-sm text-red-600">{{ $message }}</p>@enderror
            <span role="status" wire:loading wire:target="favicon" class="text-sm text-zinc-500">Mengunggah favicon…</span>
        </div>
        <div class="flex items-center justify-end gap-3"><span role="status" wire:loading wire:target="save" class="text-sm text-zinc-500">Menyimpan…</span><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan identitas</flux:button></div>
    </form>
</section>
