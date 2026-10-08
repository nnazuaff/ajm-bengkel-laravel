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
        <div class="flex items-center justify-end gap-3"><span role="status" wire:loading wire:target="save" class="text-sm text-zinc-500">Menyimpan…</span><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan identitas</flux:button></div>
    </form>
</section>
