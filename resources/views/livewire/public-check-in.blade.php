<section class="space-y-6">
<header><flux:heading size="xl" level="1">Check-in di bengkel</flux:heading><flux:text class="mt-2">Sudah di AJM? Minta kode bengkel ke petugas, lalu isi formulir ini. Petugas akan memeriksa motor dan keluhan kamu.</flux:text></header>
@if($submitted)
<div class="workshop-panel space-y-4" role="status" @if(!in_array($status, ['cancelled','expired']) && !$needsLogin) wire:poll.5s="refreshStatus" @endif>
    @if($status === 'cancelled')
        <h2 class="text-lg font-semibold">Check-in dibatalkan</h2><p>Tanya ke petugas kalau kamu masih ingin menyerahkan motor untuk servis.</p>
    @elseif($status === 'expired')
        <h2 class="text-lg font-semibold">Sesi check-in kedaluwarsa</h2><p>Minta bantuan petugas untuk melanjutkan check-in. Data yang sudah diterima tetap tersimpan.</p>
    @elseif($needsLogin)
        <h2 class="text-lg font-semibold">Check-in sudah dikonfirmasi</h2><p>Untuk melindungi riwayat servis kamu, masuk ke akun pelanggan yang sudah ada. Kalau kamu nggak bisa masuk, minta bantuan pemilik bengkel untuk memulihkan akses akun.</p><flux:button href="{{ route('login') }}" variant="primary">Masuk ke dashboard</flux:button>
    @else
        <h2 class="text-lg font-semibold">Tunggu konfirmasi mekanik</h2><p>Check-in kamu sudah terkirim. Tetap buka halaman ini sambil petugas memeriksa identitas dan motor kamu. Setelah dikonfirmasi, kamu bisa lanjut ke portal pelanggan; akun lama mungkin perlu login lebih dulu.</p><p class="text-sm text-zinc-500">Kalau belum ada akun, akun baru dibuat dari data yang kamu isi. Akun lama tidak ditimpa; email dan kata sandinya tidak diubah lewat check-in. Tidak perlu mengirim ulang formulir.</p>
    @endif
</div>
@else<form wire:submit="submit" class="workshop-panel space-y-5">
<flux:input wire:model="code" label="Kode bengkel" inputmode="numeric" maxlength="6" required autocomplete="off" description="Masukkan 6 digit dari petugas. Ini kode bengkel, bukan OTP akun. Jangan berikan OTP atau kata sandi ke petugas." />
@guest
<flux:input wire:model="name" label="Nama" required maxlength="120" autocomplete="name" />
<flux:input wire:model="phone" label="Telepon / WhatsApp" type="tel" required maxlength="30" placeholder="081234567890" autocomplete="tel" />
<flux:input wire:model="email" label="Email akun" type="email" required maxlength="254" autocomplete="email" />
<flux:input wire:model="password" label="Password akun" type="password" required autocomplete="new-password" />
<flux:input wire:model="password_confirmation" label="Konfirmasi password" type="password" required autocomplete="new-password" />
<p class="text-sm">Sudah punya akun? <a href="{{ route('login') }}" class="underline">Masuk dahulu</a>. Untuk akun baru, email dan kata sandi yang kamu isi dipakai untuk login berikutnya. Formulir ini tidak mengganti email atau kata sandi akun lama.</p>
@else
<p class="text-sm">Check-in memakai kontak dari akun kamu. Cukup isi kode bengkel, nggak perlu daftar ulang.</p>
@endguest
<div class="flex flex-wrap items-center gap-3"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Kirim check-in</flux:button><span wire:loading wire:target="submit" role="status">Mengirim…</span></div>
</form>@endif
</section>
