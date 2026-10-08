<x-layouts::app :title="'Dashboard'">
    <div class="mx-auto max-w-7xl space-y-6">
        <header class="workshop-page-heading">
            <div>
                <flux:heading size="xl" level="1">Dashboard bengkel</flux:heading>
                <flux:text class="mt-1">{{ now()->translatedFormat('l, d F Y') }} · {{ auth()->user()->role->label() }}</flux:text>
            </div>
            <flux:button variant="primary" :href="route('services.index')" wire:navigate>Buka antrean servis</flux:button>
        </header>

        @php
            $labels = ['waiting' => 'Menunggu', 'inspection' => 'Pemeriksaan', 'in_progress' => 'Dikerjakan', 'waiting_part' => 'Menunggu part', 'ready_for_pickup' => 'Siap diambil', 'completed_today' => 'Selesai hari ini'];
        @endphp
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
            @foreach ($labels as $key => $label)
                <div class="workshop-panel">
                    <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $label }}</p>
                    <p class="mt-3 text-3xl font-semibold tabular-nums">{{ $metrics[$key] }}</p>
                </div>
            @endforeach
        </div>
        <div class="flex flex-wrap justify-between gap-2 text-sm text-zinc-600 dark:text-zinc-400">
            <p>{{ $metrics['received_today'] }} servis diterima hari ini.</p>
            @if (isset($metrics['bookings_today']))<a href="{{ route('bookings.index') }}" wire:navigate class="underline underline-offset-4">{{ $metrics['bookings_today'] }} booking dijadwalkan hari ini</a>@endif
            @if (auth()->user()->role === \App\Enums\Role::Mechanic)<p>Hanya order yang ditugaskan kepada Anda.</p>@endif
        </div>

        @if (isset($metrics['low_stock']))
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <a href="{{ route('inventory.index') }}" wire:navigate class="workshop-panel"><p class="text-sm text-zinc-500">Stok menipis</p><p class="mt-2 text-2xl font-semibold">{{ $metrics['low_stock'] }}</p></a>
                <a href="{{ route('inventory.index') }}" wire:navigate class="workshop-panel"><p class="text-sm text-zinc-500">Stok habis</p><p class="mt-2 text-2xl font-semibold">{{ $metrics['out_of_stock'] }}</p></a>
                <a href="{{ route('receipts.index') }}" wire:navigate class="workshop-panel"><p class="text-sm text-zinc-500">Bon belum lunas</p><p class="mt-2 text-2xl font-semibold">{{ $metrics['unpaid_receipts'] }}</p></a>
                <a href="{{ route('payments.index') }}" wire:navigate class="workshop-panel"><p class="text-sm text-zinc-500">Pembayaran aktif hari ini</p><p class="mt-2 text-xl font-semibold">Rp {{ str_replace('.', ',', $metrics['revenue_today']) }}</p></a>
            </div>
        @endif
        <section class="space-y-3" aria-labelledby="latest-service-heading">
            <flux:heading id="latest-service-heading" level="2">Servis terbaru</flux:heading>
            <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
                <table class="workshop-table">
                    <caption class="sr-only">Enam order servis terbaru</caption>
                    <thead><tr><th scope="col">Order</th><th scope="col">Motor</th><th scope="col">Pelanggan</th><th scope="col">Mekanik</th><th scope="col">Status</th></tr></thead>
                    <tbody>
                        @forelse ($latestOrders as $order)
                            <tr>
                                <td><a href="{{ route('services.detail', $order->id) }}" wire:navigate class="font-medium underline underline-offset-4">{{ $order->service_number }}</a><p class="mt-1 text-xs text-zinc-500">{{ $order->received_at->format('d/m/Y H:i') }}</p></td>
                                <td><p class="font-medium">{{ $order->vehicle->license_plate }}</p><p class="mt-1 text-zinc-500">{{ $order->vehicle->brand }} {{ $order->vehicle->model }}</p></td>
                                <td>{{ $order->customer->name }}</td>
                                <td>{{ $order->mechanic?->name ?? 'Belum ditugaskan' }}</td>
                                <td><flux:badge size="sm" :color="$order->status->color()">{{ $order->status->label() }}</flux:badge></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="!py-10 text-center text-zinc-500">Belum ada servis{{ auth()->user()->role === \App\Enums\Role::Mechanic ? ' yang ditugaskan kepada Anda' : '' }}.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
        @if($latestActivities->isNotEmpty())
            <section class="workshop-panel"><flux:heading level="2">Aktivitas terbaru</flux:heading><ul class="mt-3 divide-y divide-zinc-100 dark:divide-zinc-800">@foreach($latestActivities as $activity)<li class="flex flex-wrap justify-between gap-2 py-2 text-sm"><span>{{ $activity->action }} · {{ $activity->actor?->name ?? 'Sistem' }}</span><span class="text-zinc-500">{{ $activity->created_at->format('d/m H:i') }}</span></li>@endforeach</ul></section>
        @endif
    </div>
</x-layouts::app>
