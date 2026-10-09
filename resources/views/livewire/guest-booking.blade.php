<section class="space-y-6">
    <header><flux:heading size="xl" level="1">Booking tanpa akun</flux:heading><flux:text class="mt-2">Isi kontak, motor, dan jadwal kedatangan. Permintaan menunggu konfirmasi bengkel.</flux:text></header>
    @if ($submitted)
        <div role="status" class="workshop-panel space-y-3"><h2 class="text-lg font-semibold">Booking berhasil diajukan</h2><p>Petugas akan mengonfirmasi melalui kontak yang Anda tulis. Data kendaraan akan diperiksa saat datang.</p><flux:button href="{{ route('home') }}" wire:navigate>Kembali ke beranda</flux:button></div>
    @else
        <form wire:submit="submit" class="workshop-panel space-y-6">
            <x-validation-summary :inline="['form.name', 'form.phone', 'form.email', 'form.license_plate', 'form.brand', 'form.model', 'form.year', 'form.current_mileage', 'form.booking_date', 'form.arrival_time', 'form.service_type', 'form.complaint', 'form.notes']" />
            <fieldset class="space-y-4"><legend class="mb-3 font-semibold">Kontak</legend><div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.name" label="Nama" required maxlength="120" autocomplete="name" />
                <flux:input wire:model="form.phone" label="Telepon / WhatsApp" type="tel" required maxlength="30" autocomplete="tel" placeholder="081234567890" />
                <flux:input wire:model="form.email" label="Email (opsional)" type="email" maxlength="254" autocomplete="email" />
            </div></fieldset>
            <fieldset class="space-y-4"><legend class="mb-3 font-semibold">Motor</legend><div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.license_plate" label="Pelat nomor" required maxlength="20" placeholder="D 1234 ABC" />
                <flux:input wire:model="form.brand" label="Merek" required maxlength="60" />
                <flux:input wire:model="form.model" label="Model / tipe" required maxlength="100" />
                <flux:input wire:model="form.year" label="Tahun (opsional)" type="number" min="1900" max="{{ now()->year + 1 }}" />
                <flux:input wire:model="form.current_mileage" label="Kilometer saat ini" type="number" min="0" max="2147483647" required />
            </div></fieldset>
            <fieldset class="space-y-4"><legend class="mb-3 font-semibold">Jadwal dan keluhan</legend><div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="form.booking_date" label="Tanggal kedatangan" type="date" min="{{ now()->toDateString() }}" max="{{ now()->addDays(90)->toDateString() }}" required />
                <flux:input wire:model="form.arrival_time" label="Jam kedatangan (WIB)" type="time" min="08:00" max="17:00" required />
                <flux:input wire:model="form.service_type" label="Jenis servis" required maxlength="100" placeholder="Servis berkala" />
                <div class="sm:col-span-2"><flux:textarea wire:model="form.complaint" label="Keluhan" rows="3" maxlength="5000" required /></div>
                <div class="sm:col-span-2"><flux:textarea wire:model="form.notes" label="Catatan tambahan (opsional)" rows="2" maxlength="5000" /></div>
            </div></fieldset>
            <div class="flex flex-wrap items-center justify-end gap-3"><span wire:loading wire:target="submit" role="status">Mengirim…</span><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Ajukan booking</flux:button></div>
        </form>
        <p class="text-sm text-zinc-600 dark:text-zinc-400">Punya akun? <a href="{{ route('booking.mine') }}" class="underline" wire:navigate>Booking dengan kendaraan tersimpan</a>. Akun memberi akses histori setelah identitas terverifikasi.</p>
    @endif
</section>
