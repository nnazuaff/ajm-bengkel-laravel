<div>
    <flux:modal name="vehicle-editor" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="vehicle-editor-heading">
    @if ($showForm)
        <form wire:submit="save" class="space-y-5" aria-labelledby="vehicle-editor-heading" x-init="$nextTick(() => $el.querySelector('input, select')?.focus())">
            <flux:heading size="lg" level="2" id="vehicle-editor-heading">{{ $editingId ? 'Edit kendaraan' : 'Tambah kendaraan' }}</flux:heading>
            @if ($customers->isEmpty())
                <flux:text>Tambahkan pelanggan melalui menu Pelanggan sebelum mendaftarkan motor.</flux:text>
            @endif
            <div class="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <div class="md:col-span-2 lg:col-span-3">
                    <flux:select wire:model="form.customer_id" label="Pelanggan" placeholder="Pilih pelanggan" required>
                        @foreach ($customers as $customer)
                            <option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:input wire:model="form.license_plate" label="Pelat nomor" required maxlength="30" placeholder="B 1234 ABC" />
                <flux:input wire:model="form.brand" label="Merek" required maxlength="60" placeholder="Honda" />
                <flux:input wire:model="form.model" label="Tipe motor" required maxlength="100" placeholder="Vario 125" />
                <flux:input wire:model="form.year" label="Tahun (opsional)" type="number" min="1900" max="{{ now()->year + 1 }}" />
                <flux:input wire:model="form.color" label="Warna (opsional)" maxlength="50" />
                <flux:input wire:model="form.latest_mileage" label="Odometer (km)" type="number" min="0" max="2147483647" required />
                <flux:input wire:model="form.chassis_number" label="Nomor rangka (opsional)" maxlength="100" />
                <flux:input wire:model="form.engine_number" label="Nomor mesin (opsional)" maxlength="100" />
                <div class="md:col-span-2 lg:col-span-3"><flux:textarea wire:model="form.notes" label="Catatan (opsional)" rows="2" maxlength="5000" /></div>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button type="button" wire:click="closeForm">Batal</flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">Simpan kendaraan</flux:button>
            </div>
        </form>
    @endif
    </flux:modal>
</div>
