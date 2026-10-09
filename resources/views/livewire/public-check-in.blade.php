<section class="space-y-6">
<header><flux:heading size="xl" level="1">Check-in di bengkel</flux:heading><flux:text class="mt-2">Minta kode kepada petugas. Cukup isi kontak; motor dan keluhan dikonfirmasi petugas.</flux:text></header>
@if($submitted)
<div class="workshop-panel space-y-4" role="status" @if(!in_array($status, ['cancelled','expired']) && !$needsLogin) wire:poll.5s="refreshStatus" @endif>
    @if($status === 'cancelled')
        <h2 class="text-lg font-semibold">Check-in dibatalkan</h2><p>Hubungi petugas untuk bantuan penerimaan kendaraan.</p>
    @elseif($status === 'expired')
        <h2 class="text-lg font-semibold">Sesi check-in kedaluwarsa</h2><p>Hubungi petugas. Data yang sudah diterima tetap tersimpan.</p>
    @elseif($needsLogin)
        <h2 class="text-lg font-semibold">Check-in sudah dikonfirmasi</h2><p>Untuk melindungi histori, masuk ke akun pelanggan yang sudah ada. Jika belum punya akses, minta bantuan owner untuk pemulihan akun.</p><flux:button href="{{ route('login') }}" variant="primary">Masuk ke dashboard</flux:button>
    @else
        <h2 class="text-lg font-semibold">Tunggu konfirmasi mekanik</h2><p>Check-in berhasil. Tetap buka halaman ini; setelah identitas dan kendaraan dikonfirmasi, Anda otomatis diarahkan ke dashboard pelanggan.</p><p class="text-sm text-zinc-500">Akun baru dibuat otomatis bila belum ada. Akun lama tidak ditimpa. Tidak perlu mengirim ulang formulir.</p>
    @endif
</div>
@else<form wire:submit="submit" class="workshop-panel space-y-5">
<flux:input wire:model="code" label="Kode bengkel" inputmode="numeric" maxlength="6" required autocomplete="off" description="6 digit dari petugas. Kode bukan OTP akun." />
@guest
<flux:input wire:model="name" label="Nama" required maxlength="120" autocomplete="name" />
<flux:input wire:model="phone" label="Telepon / WhatsApp" type="tel" required maxlength="30" placeholder="081234567890" autocomplete="tel" />
<flux:input wire:model="email" label="Email akun" type="email" required maxlength="254" autocomplete="email" />
<flux:input wire:model="password" label="Password akun" type="password" required autocomplete="new-password" />
<flux:input wire:model="password_confirmation" label="Konfirmasi password" type="password" required autocomplete="new-password" />
<p class="text-sm">Sudah punya akun? <a href="{{ route('login') }}" class="underline">Masuk dahulu</a>. Email dan password ini digunakan untuk login lagi nanti.</p>
@else
<p class="text-sm">Check-in memakai kontak akun Anda; tidak perlu mendaftar ulang.</p>
@endguest
<div class="flex flex-wrap items-center gap-3"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Kirim check-in</flux:button><span wire:loading wire:target="submit" role="status">Mengirim…</span></div>
</form>@endif
</section>
