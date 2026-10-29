@props(['guest' => false])

<div x-data="{
    busy: false, number: '', url: null, notice: '',
    async send(wire) {
        if (this.busy) return;
        this.busy = true;
        this.notice = '';
        let tab = null;
        try {
            // Open during the user's submit gesture; navigating after await can be blocked.
            tab = window.open('about:blank', '_blank');
            if (tab) {
                tab.opener = null;
                tab.document.title = 'Menyiapkan konfirmasi booking';
                tab.document.body.textContent = 'Menyimpan booking. WhatsApp dibuka setelah berhasil.';
            }
            const result = await wire.submit();
            if (!result) { tab?.close(); return; }
            this.number = result.number;
            this.url = result.url;
            if (!this.url) {
                tab?.close();
                this.notice = 'Booking tersimpan. Nomor WhatsApp bengkel belum tersedia; tunggu petugas menghubungi kamu.';
            } else if (tab && !tab.closed) {
                tab.location.replace(this.url);
                this.notice = 'WhatsApp sudah dibuka. Tekan Kirim untuk meneruskan detail booking kamu.';
            } else {
                this.notice = 'Tab WhatsApp tidak terbuka. Tekan tombol konfirmasi di bawah.';
            }
        } catch {
            tab?.close();
            this.notice = 'Permintaan belum dapat dipastikan. Periksa koneksi dan booking kamu sebelum mengajukan ulang.';
        } finally {
            this.busy = false;
        }
    }
}">
    <div x-cloak x-show="busy || number || notice" role="status" aria-live="polite" class="workshop-panel mb-5 space-y-5" @if($guest) x-bind:class="number ? 'mx-auto w-full max-w-xl' : ''" @endif>
        <header x-show="number" class="space-y-3">
            <span class="inline-flex rounded-full bg-amber-100 px-3 py-1 text-xs font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200">Menunggu konfirmasi bengkel</span>
            <flux:heading :level="$guest ? 1 : 2" size="xl">Booking berhasil diajukan</flux:heading>
            <p class="text-sm text-zinc-600 dark:text-zinc-400">Nomor booking: <span x-text="number" class="break-all font-semibold text-zinc-900 dark:text-zinc-100"></span></p>
        </header>
        <div class="space-y-2 text-sm leading-relaxed text-zinc-600 dark:text-zinc-400">
            <p x-text="notice"></p>
            <p x-show="number">Tunggu konfirmasi AJM sebelum datang ke bengkel.</p>
        </div>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
        <a x-show="busy || url" x-bind:href="url" x-bind:aria-disabled="busy" x-bind:class="busy ? 'pointer-events-none opacity-60' : ''" target="_blank" rel="noopener noreferrer" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-lg bg-emerald-700 px-4 py-2 text-sm font-medium text-white">
            <flux:icon.arrow-path x-show="busy" class="size-4 animate-spin" />
            <span x-text="busy ? 'Menyiapkan WhatsApp…' : 'Konfirmasi via WhatsApp'"></span>
        </a>
        @if($guest)
            <a x-show="number" href="{{ route('home') }}" wire:navigate class="inline-flex min-h-11 items-center justify-center rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 underline-offset-4 hover:underline dark:text-zinc-300">Kembali ke beranda</a>
        @endif
        </div>
    </div>
    {{ $slot }}
</div>
