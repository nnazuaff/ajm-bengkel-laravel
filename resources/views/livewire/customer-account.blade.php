<div>
    <flux:modal name="customer-account" wire:model="showForm"
        class="w-[calc(100%-2rem)] max-w-2xl max-h-[calc(100dvh-2rem)] overflow-y-auto"
        aria-labelledby="customer-account-heading">
        @if ($showForm)
            <section class="space-y-4" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
                <div>
                    <flux:heading size="lg" level="2" id="customer-account-heading">Akun portal pelanggan
                    </flux:heading>
                    <flux:text class="mt-1">{{ $customer->name }}. Tidak dicocokkan otomatis melalui telepon, email,
                        atau booking.</flux:text>
                </div>
                @if ($status)
                    <p role="status" class="text-sm text-emerald-800 dark:text-emerald-200">{{ $status }}</p>
                @endif
                <div class="text-sm">
                    <p class="font-medium">Akun terhubung saat ini</p>
                    @if ($customer->user)
                        <p>{{ $customer->user->name }} ({{ $customer->user->email }})</p>
                        @if ($customer->user->trashed())
                            <p class="text-red-700 dark:text-red-300">Akun diarsipkan. Akses portal tidak aktif.</p>
                        @endif
                    @else
                        <p>Belum terhubung. Pelanggan belum dapat melihat riwayat kendaraan.</p>
                    @endif
                </div>
                <form wire:submit="save"
                    wire:confirm="Ubah hubungan akun ini? Akun terpilih akan memperoleh akses riwayat pelanggan; akun sebelumnya kehilangan akses. Pilihan tanpa akun melepas akses portal. Pastikan verifikasi kepemilikan sudah dilakukan."
                    class="space-y-4">
                    <flux:input wire:model.live.debounce.300ms="search" label="Cari akun berdasarkan nama atau email"
                        type="search" maxlength="120" />
                    @if ($hasMore)
                        <p role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Hasil dibatasi 50 akun.
                            Persempit pencarian nama atau email.</p>
                    @elseif ($accounts->isEmpty())
                        <p role="status" class="text-sm text-zinc-600 dark:text-zinc-300">Tidak ada akun yang cocok.
                        </p>
                    @endif
                    <div>
                        <label for="customer-account-{{ $customerId }}"
                            class="mb-2 block text-sm font-medium">{{ config('fortify.require_email_verification') ? 'Akun pelanggan terverifikasi' : 'Akun pelanggan aktif' }}</label>
                        <select id="customer-account-{{ $customerId }}" wire:model.live="userId"
                            aria-describedby="customer-account-help-{{ $customerId }}"
                            @error('userId') aria-invalid="true" @enderror
                            class="w-full rounded-lg border border-zinc-300 bg-white p-2.5 text-sm text-zinc-900 focus:outline-2 focus:outline-zinc-600 dark:border-zinc-600 dark:bg-zinc-800 dark:text-zinc-100">
                            <option value="">Tanpa akun (lepas hubungan)</option>
                            @if ($userId !== '' && !$accounts->contains('id', (int) $userId))
                                <option value="{{ $userId }}" @disabled(!$selectedAccount)>
                                    {{ $selectedAccount ? $selectedAccount->name . ' (' . $selectedAccount->email . ')' : 'Pilihan tidak memenuhi syarat. Pilih akun lain atau lepas hubungan.' }}
                                </option>
                            @endif
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }} ({{ $account->email }})
                                </option>
                            @endforeach
                        </select>
                        <p id="customer-account-help-{{ $customerId }}"
                            class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            @if (config('fortify.require_email_verification'))
                                Hanya akun pelanggan aktif, email terverifikasi, dan belum terhubung ke pelanggan lain.
                            @else
                                Hanya akun pelanggan aktif dan belum terhubung ke pelanggan lain. Verifikasi email tidak
                                diwajibkan; verifikasi identitas dan kepemilikan tetap wajib.
                            @endif
                        </p>
                        @error('userId')
                            <p role="alert" class="mt-2 text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                        @enderror
                    </div>
                    <label class="flex items-start gap-3 text-sm">
                        <input type="checkbox" wire:model="ownershipVerified" class="mt-1 rounded border-zinc-400" />
                        <span>Saya telah memverifikasi identitas dan kepemilikan kendaraan secara langsung, serta
                            memastikan hubungan akun ini benar (termasuk saat melepas akun).</span>
                    </label>
                    @error('ownershipVerified')
                        <p role="alert" class="text-sm text-red-700 dark:text-red-300">{{ $message }}</p>
                    @enderror
                    <div class="flex flex-wrap items-center justify-end gap-3">
                        <span wire:loading wire:target="save" role="status"
                            class="text-sm text-zinc-600 dark:text-zinc-300">Menyimpan hubungan akun…</span>
                        <flux:button type="button" wire:click="closeForm">Batal</flux:button>
                        <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan hubungan
                        </flux:button>
                    </div>
                </form>
            </section>
        @endif
    </flux:modal>
</div>
