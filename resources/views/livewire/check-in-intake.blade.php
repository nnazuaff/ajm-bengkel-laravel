<div>
    <flux:modal :closable="false" name="check-in-intake" wire:model="showForm"
        x-on:close="$dispatch('check-in-intake-closed')"
        class="w-[calc(100%-2rem)] max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto"
        aria-labelledby="check-in-intake-heading">
        @if ($checkIn)
            <form wire:submit="submit" class="space-y-5" x-init="$nextTick(() => $el.querySelector('select')?.focus())">
                <flux:heading level="2" id="check-in-intake-heading">Terima servis · {{ $checkIn->customer->name }}
                </flux:heading>
                <x-validation-summary :inline="[
                    'form.vehicle_id',
                    'form.vehicle.license_plate',
                    'form.vehicle.brand',
                    'form.vehicle.model',
                    'form.vehicle.year',
                    'form.vehicle.color',
                    'form.current_mileage',
                    'form.complaint',
                    'form.mechanic_id',
                    'form.identity_verified',
                ]" />
                <flux:select wire:model.live="form.vehicle_id" label="Motor pelanggan">
                    <option value="">Tambah motor baru</option>
                    @foreach ($vehicles as $vehicle)
                        <option value="{{ $vehicle->id }}">{{ $vehicle->license_plate }} · {{ $vehicle->brand }}
                            {{ $vehicle->model }} · {{ $vehicle->latest_mileage }} km</option>
                    @endforeach
                </flux:select>
                @if (!$form['vehicle_id'])
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="form.vehicle.license_plate" label="Pelat nomor" required
                            maxlength="20" />
                        <flux:input wire:model="form.vehicle.brand" label="Merek" required maxlength="60" />
                        <flux:input wire:model="form.vehicle.model" label="Model / tipe" required maxlength="100" />
                        <flux:input wire:model="form.vehicle.year" label="Tahun (opsional)" type="number"
                            min="1900" max="{{ now()->year + 1 }}" />
                        <flux:input wire:model="form.vehicle.color" label="Warna (opsional)" maxlength="50" />
                    </div>
                @endif
                <flux:input wire:model="form.current_mileage" label="Kilometer saat ini" type="number" min="0"
                    max="2147483647" required />
                <flux:textarea wire:model="form.complaint" label="Keluhan pelanggan" required rows="3"
                    maxlength="5000" />
                @if ($manager)
                    <flux:select wire:model="form.mechanic_id" label="Mekanik (opsional)">
                        <option value="">Belum ditugaskan</option>
                        @foreach ($mechanics as $mechanic)
                            <option value="{{ $mechanic->id }}">{{ $mechanic->name }}</option>
                        @endforeach
                    </flux:select>
                @else<p class="text-sm">Servis ditugaskan kepada Anda.</p>
                @endif
                @if ($checkIn->account_id && $checkIn->requires_identity_verification)
                    <flux:checkbox wire:model="form.identity_verified"
                        label="Saya sudah memverifikasi identitas pelanggan, kepemilikan motor, dan browser check-in di hadapan pelanggan." />
                    <p class="text-sm text-zinc-500">Konfirmasi ini mengaktifkan dashboard di browser pelanggan. Jangan
                        konfirmasi hanya berdasarkan kesamaan nomor telepon.</p>
                @endif
                <div class="flex flex-wrap justify-end gap-3">
                    <flux:button type="button" wire:click="closeForm">Batal</flux:button>
                    <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Buat servis</flux:button>
                </div>
            </form>
        @endif
    </flux:modal>
</div>
