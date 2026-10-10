<x-layouts::auth :title="__('Masuk')">
    <div class="flex flex-col gap-5">
        <x-auth-header :title="__('Masuk ke akun')" :description="__('Masukkan email dan kata sandi akun kamu. Kalau lupa kata sandi, pilih Lupa kata sandi? di bawah.')" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
            @csrf

            <flux:input
                name="email"
                :label="__('Email')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="nama@contoh.com"
            />

            <flux:input
                name="password"
                :label="__('Kata sandi')"
                type="password"
                required
                autocomplete="current-password"
                :placeholder="__('Masukkan kata sandi')"
                viewable
            />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <flux:checkbox name="remember" :label="__('Ingat saya')" :checked="old('remember')" />
                @if (Route::has('password.request'))
                    <flux:link class="text-sm" :href="route('password.request')" wire:navigate>
                        {{ __('Lupa kata sandi?') }}
                    </flux:link>
                @endif
            </div>

            <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                {{ __('Masuk') }}
            </flux:button>
        </form>

        @if (Route::has('register'))
            <div class="text-center text-sm text-zinc-600 dark:text-zinc-400">
                <span>{{ __('Belum punya akun pelanggan?') }}</span>
                <flux:link :href="route('register')" wire:navigate>{{ __('Daftar') }}</flux:link>
            </div>
        @endif
    </div>
</x-layouts::auth>
