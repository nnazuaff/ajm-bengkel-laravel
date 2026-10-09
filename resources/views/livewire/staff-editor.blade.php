<div>
    <flux:modal :closable="false" name="staff-editor" wire:model="showForm" class="w-[calc(100%-2rem)] max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="staff-form-heading">
    @if ($showForm)
    <form wire:submit="save" class="space-y-5" aria-labelledby="staff-form-heading" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
        <flux:heading size="lg" level="2" id="staff-form-heading">{{ $editingId ? 'Edit staf' : 'Tambah staf' }}</flux:heading>
        <x-validation-summary :inline="$editingId ? ['name', 'role'] : ['name', 'role', 'email', 'password', 'password_confirmation']" />
        <div class="grid gap-5 md:grid-cols-2">
            <flux:input wire:model="name" label="Nama staf" required maxlength="255" autocomplete="name" />
            <flux:select wire:model="role" label="Peran" required><option value="mechanic">Mekanik</option><option value="admin">Admin / Kasir</option><option value="owner">Pemilik</option></flux:select>
            @if (!$editingId)
            <flux:input wire:model="email" label="Email akun" type="email" required maxlength="254" autocomplete="off" />
            <div class="md:col-span-2 grid gap-5 md:grid-cols-2"><flux:input wire:model="password" label="Kata sandi (minimal 12 karakter)" type="password" required minlength="12" maxlength="255" autocomplete="new-password" /><flux:input wire:model="password_confirmation" label="Ulangi kata sandi" type="password" required minlength="12" maxlength="255" autocomplete="new-password" /></div>
            <flux:text class="md:col-span-2">Akun staf dibuat terverifikasi oleh pemilik. Berikan kredensial secara pribadi; jangan gunakan kata sandi bersama.</flux:text>
            @endif
        </div>
        <div class="flex items-center justify-end gap-3"><span wire:loading wire:target="save" role="status" class="text-sm text-zinc-500">Menyimpan…</span><flux:button wire:click="cancel" type="button">Batal</flux:button><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan staf</flux:button></div>
    </form>
    @endif
    </flux:modal>
</div>
