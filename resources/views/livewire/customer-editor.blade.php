<div>
    <flux:modal :closable="false" name="customer-editor" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="customer-editor-heading">
    @if ($showForm)
        <form wire:submit="save" class="space-y-5" aria-labelledby="customer-editor-heading" x-init="$nextTick(() => $el.querySelector('input, select')?.focus())">
            <flux:heading size="lg" level="2" id="customer-editor-heading">{{ $editingId ? 'Edit pelanggan' : 'Tambah pelanggan' }}</flux:heading>
            <div class="grid gap-5 md:grid-cols-2">
                <flux:input wire:model="form.name" label="Nama pelanggan" required maxlength="120" autocomplete="name" />
                <flux:input wire:model="form.phone" label="Telepon / WhatsApp" type="tel" required maxlength="30" placeholder="0812 3456 7890" autocomplete="tel" />
                <flux:input wire:model="form.email" label="Email (opsional)" type="email" maxlength="254" autocomplete="email" />
                <flux:textarea wire:model="form.address" label="Alamat (opsional)" rows="2" maxlength="2000" autocomplete="street-address" />
                <div class="md:col-span-2"><flux:textarea wire:model="form.notes" label="Catatan (opsional)" rows="2" maxlength="5000" /></div>
            </div>
            <div class="flex justify-end gap-3">
                <flux:button type="button" wire:click="closeForm">Batal</flux:button>
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="save">Simpan pelanggan</flux:button>
            </div>
        </form>
    @endif
    </flux:modal>
</div>
