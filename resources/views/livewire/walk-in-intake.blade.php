<div>
    <flux:modal :closable="false" name="walk-in-intake" wire:model="showIntake" class="w-[calc(100%-2rem)] max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="intake-heading">
        @if ($showIntake)
        <form wire:submit="receiveWalkIn" class="space-y-5" aria-labelledby="intake-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:heading level="2" id="intake-heading">Terima servis walk-in</flux:heading>
                <div class="flex flex-wrap gap-2">
                    <flux:button type="button" size="sm" wire:click="useExistingVehicle" :aria-pressed="! $newVehicle">Cari motor terdaftar</flux:button>
                    <flux:button type="button" size="sm" wire:click="useNewVehicle" :aria-pressed="$newVehicle">Motor baru</flux:button>
                </div>
            </div>
            @if ($selectedVehicle)
                <div class="rounded-lg bg-zinc-50 p-4 text-sm dark:bg-zinc-800">
                    <p class="font-semibold">{{ $selectedVehicle->license_plate }} · {{ $selectedVehicle->brand }} {{ $selectedVehicle->model }}</p>
                    <p class="mt-1">{{ $selectedVehicle->customer->name }} · {{ $selectedVehicle->customer->phone }}</p>
                    <p class="mt-1 text-zinc-500">Kilometer terakhir: {{ number_format($selectedVehicle->latest_mileage, 0, ',', '.') }} km</p>
                </div>
            @elseif ($newVehicle)
                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">Pelanggan</legend>
                    <p class="text-sm text-zinc-500">Telepon yang sudah terdaftar akan memakai pelanggan tersebut tanpa mengubah profilnya.</p>
                    <div class="grid gap-5 md:grid-cols-2">
                        <flux:input wire:model="intake.customer.name" label="Nama pelanggan" required maxlength="120" autocomplete="name" />
                        <flux:input wire:model="intake.customer.phone" label="Telepon / WhatsApp" type="tel" required maxlength="30" autocomplete="tel" placeholder="0812 3456 7890" />
                        <flux:input wire:model="intake.customer.email" label="Email (opsional)" type="email" maxlength="254" autocomplete="email" />
                        <flux:textarea wire:model="intake.customer.address" label="Alamat (opsional)" rows="2" maxlength="2000" autocomplete="street-address" />
                    </div>
                    <flux:error name="intake.customer" />
                </fieldset>
                <fieldset class="space-y-4">
                    <legend class="mb-2 font-medium">Motor baru</legend>
                    <div class="grid gap-5 md:grid-cols-3">
                        <flux:input wire:model="intake.vehicle.license_plate" label="Pelat nomor" required maxlength="20" placeholder="B 1234 ABC" />
                        <flux:input wire:model="intake.vehicle.brand" label="Merek" required maxlength="60" placeholder="Honda" />
                        <flux:input wire:model="intake.vehicle.model" label="Model / tipe" required maxlength="100" placeholder="Vario 125" />
                        <flux:input wire:model="intake.vehicle.year" label="Tahun (opsional)" type="number" min="1900" max="{{ now()->year + 1 }}" step="1" />
                        <flux:input wire:model="intake.vehicle.color" label="Warna (opsional)" maxlength="50" />
                    </div>
                    <flux:error name="intake.vehicle" />
                </fieldset>
            @else
                <flux:input wire:model.live.debounce.300ms="intakeSearch" label="Cari motor terdaftar" type="search" maxlength="120" placeholder="Pelat nomor atau telepon / WhatsApp" />
                <p class="text-sm text-zinc-500">Ketik minimal 2 karakter, lalu pilih motor yang sesuai.</p>
                <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
                    @foreach ($vehicleMatches as $vehicle)
                        <li wire:key="intake-vehicle-{{ $vehicle->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <div class="text-sm"><p class="font-semibold">{{ $vehicle->license_plate }} · {{ $vehicle->brand }} {{ $vehicle->model }}</p><p class="text-zinc-500">{{ $vehicle->customer->name }} · {{ $vehicle->customer->phone }}</p></div>
                            <flux:button size="sm" type="button" wire:click="selectVehicle({{ $vehicle->id }})" aria-label="Pilih motor {{ $vehicle->license_plate }}">Pilih motor</flux:button>
                        </li>
                    @endforeach
                </ul>
                @if ($refineIntakeSearch)<p role="status" class="text-sm text-amber-700 dark:text-amber-300">Lebih dari 10 motor cocok. Perjelas pelat atau telepon pencarian.</p>@endif
            @endif
            @error('intake.vehicle_id')<p role="alert" class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>@enderror
            <div class="grid gap-5 md:grid-cols-2">
                <flux:input wire:model="intake.current_mileage" label="Kilometer saat diterima" type="number" min="{{ $selectedVehicle?->latest_mileage ?? 0 }}" max="2147483647" step="1" required />
                <flux:field>
                    <flux:label for="intake-mechanic">Mekanik (opsional)</flux:label>
                    <select id="intake-mechanic" wire:model="intake.mechanic_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                        <option value="">Belum ditugaskan</option>
                        @foreach ($mechanics as $mechanic)<option value="{{ $mechanic->id }}">{{ $mechanic->name }}</option>@endforeach
                    </select>
                    <flux:error name="intake.mechanic_id" />
                </flux:field>
                <div class="md:col-span-2"><flux:textarea wire:model="intake.complaint" label="Keluhan pelanggan" rows="3" maxlength="5000" required placeholder="Catat keluhan pelanggan, bukan diagnosis mekanik." /></div>
                <div class="md:col-span-2"><flux:textarea wire:model="intake.notes" label="Catatan penerimaan (opsional)" rows="2" maxlength="5000" /></div>
            </div>
            <div class="flex flex-wrap justify-end gap-3">
                <flux:button type="button" wire:click="closeIntake">Batal</flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="receiveWalkIn">Terima servis</flux:button>
            </div>
        </form>
    @endif

    </flux:modal>
</div>
