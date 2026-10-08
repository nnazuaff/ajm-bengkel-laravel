<div>
    <flux:modal name="booking-request" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="booking-form-heading">
        @if($showForm)
            <form wire:submit="submit" class="space-y-5" aria-labelledby="booking-form-heading" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                <flux:heading size="lg" level="2" id="booking-form-heading">Buat booking</flux:heading>
                <p class="text-sm text-zinc-500">Data ini hanya permintaan booking, bukan perubahan data pelanggan atau motor terdaftar. Jam 08:00–17:00 WIB merupakan pilihan permintaan, bukan konfirmasi slot.</p>
                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">Kontak</legend>
                    <div class="grid gap-5 md:grid-cols-2">
                        <flux:input wire:model="form.name" label="Nama" maxlength="120" required autocomplete="name" />
                        <flux:input wire:model="form.phone" label="Telepon / WhatsApp" type="tel" maxlength="20" required autocomplete="tel" placeholder="081234567890" />
                        <flux:input wire:model="form.email" label="Email (opsional)" type="email" maxlength="254" autocomplete="email" />
                    </div>
                </fieldset>
                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">Motor</legend>
                    @if($ownVehicles->isNotEmpty())
                        <div class="space-y-2"><p class="text-sm font-medium">Pilih motor saya</p><div class="flex flex-wrap gap-2">
                            @foreach($ownVehicles as $ownVehicle)<flux:button type="button" size="sm" wire:click="useVehicle({{ $ownVehicle->id }})" wire:loading.attr="disabled">{{ $ownVehicle->license_plate }} · {{ $ownVehicle->brand }} {{ $ownVehicle->model }}</flux:button>@endforeach
                        </div><p class="text-sm text-zinc-500">Data diisi dari motor terhubung. Perbarui kilometer sesuai kondisi saat ini.</p></div>
                    @endif
                    <div class="grid gap-5 md:grid-cols-3">
                        <flux:input wire:model="form.license_plate" label="Pelat nomor" maxlength="20" required placeholder="B 1234 ABC" />
                        <flux:input wire:model="form.brand" label="Merek" maxlength="60" required />
                        <flux:input wire:model="form.model" label="Model / tipe" maxlength="100" required />
                        <flux:input wire:model="form.year" label="Tahun (opsional)" type="number" min="1900" max="{{ now()->year + 1 }}" step="1" />
                        <flux:input wire:model="form.current_mileage" label="Kilometer saat ini" type="number" min="0" max="2147483647" step="1" required />
                    </div>
                </fieldset>
                <div class="grid gap-5 md:grid-cols-2">
                    <flux:input wire:model="form.booking_date" label="Tanggal kedatangan" type="date" min="{{ now()->toDateString() }}" max="{{ now()->addDays(90)->toDateString() }}" required />
                    <flux:input wire:model="form.arrival_time" label="Jam kedatangan (WIB)" type="time" min="08:00" max="17:00" required />
                    <flux:input wire:model="form.service_type" label="Jenis servis" maxlength="100" required placeholder="Servis berkala" />
                    <div class="md:col-span-2"><flux:textarea wire:model="form.complaint" label="Keluhan" rows="3" maxlength="5000" required /></div>
                    <div class="md:col-span-2"><flux:textarea wire:model="form.notes" label="Catatan tambahan (opsional)" rows="2" maxlength="5000" /></div>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-3">
                    <span role="status" wire:loading wire:target="submit" class="text-sm text-zinc-500">Mengirim…</span>
                    <flux:button type="button" wire:click="closeForm">Batal</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="submit">Ajukan booking</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
