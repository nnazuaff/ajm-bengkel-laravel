<section class="mx-auto w-full max-w-7xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <flux:heading size="xl" level="1">Servis</flux:heading>
            <flux:text class="mt-1">{{ $managesWorkshop ? 'Penerimaan motor, diagnosis, dan progres servis bengkel.' : 'Diagnosis dan progres servis yang ditugaskan kepada Anda.' }}</flux:text>
        </div>
        @if ($managesWorkshop)
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('open-walk-in-intake')" wire:loading.attr="disabled">Terima walk-in</flux:button>
        @endif
    </header>

    @if (session('status'))
        <p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div class="w-full sm:max-w-md"><flux:input data-workshop-search wire:model.live.debounce.300ms="search" label="Cari servis" type="search" maxlength="120" placeholder="Nomor servis, pelat, nama, atau telepon" icon="magnifying-glass" /></div>
        <p class="text-sm text-zinc-500" role="status">{{ $orders->total() }} servis <span wire:loading wire:target="search,statusFilter,mechanicFilter,sourceFilter" class="ml-2">· Memuat…</span></p>
    </div>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <flux:field>
            <flux:label for="service-status-filter">Status</flux:label>
            <select id="service-status-filter" wire:model.live="statusFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
            </select>
        </flux:field>
        @if ($managesWorkshop)
            <flux:field>
                <flux:label for="service-mechanic-filter">Mekanik</flux:label>
                <select id="service-mechanic-filter" wire:model.live="mechanicFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                    <option value="">Semua mekanik</option>
                    @foreach ($mechanics as $mechanic)<option value="{{ $mechanic->id }}">{{ $mechanic->name }}</option>@endforeach
                </select>
            </flux:field>
        @endif
        <flux:field>
            <flux:label for="service-source-filter">Sumber</flux:label>
            <select id="service-source-filter" wire:model.live="sourceFilter" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                <option value="">Semua sumber</option>
                @foreach ($sources as $source)<option value="{{ $source->value }}">{{ $source->label() }}</option>@endforeach
            </select>
        </flux:field>
    </div>

    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
        <table class="workshop-responsive-table workshop-table" role="table">
            <caption class="sr-only">Daftar servis bengkel</caption>
            <thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Order / diterima</th><th role="columnheader" scope="col">Motor</th><th role="columnheader" scope="col">Pelanggan</th><th role="columnheader" scope="col">Status</th><th role="columnheader" scope="col">Mekanik</th><th role="columnheader" scope="col">Tindakan</th></tr></thead>
            <tbody role="rowgroup">
                @forelse ($orders as $order)
                    <tr role="row" wire:key="service-{{ $order->id }}">
                        <td role="cell" data-label="Order / diterima"><p class="font-medium">{{ $order->service_number }}</p><p class="mt-1 whitespace-nowrap text-xs text-zinc-500">{{ $order->received_at->format('d/m/Y H:i') }} · {{ $order->source->label() }}</p></td>
                        <td role="cell" data-label="Motor"><p class="font-semibold">{{ $order->vehicle->license_plate }}</p><p class="mt-1 text-zinc-500">{{ $order->vehicle->brand }} {{ $order->vehicle->model }}</p></td>
                        <td role="cell" data-label="Pelanggan"><p>{{ $order->customer->name }}</p><p class="mt-1 text-zinc-500">{{ $order->customer->phone }}</p></td>
                        <td role="cell" data-label="Status" class="whitespace-nowrap"><flux:badge :color="$order->status->color()" size="sm">{{ $order->status->label() }}</flux:badge></td>
                        <td role="cell" data-label="Mekanik">{{ $order->mechanic?->name ?? 'Belum ditugaskan' }}</td>
                        <td role="cell" data-label="Tindakan"><flux:button size="sm" variant="ghost" :href="route('services.detail', $order)" wire:navigate aria-label="Buka detail {{ $order->service_number }}">Detail</flux:button></td>
                    </tr>
                @empty
                    <tr role="row"><td role="cell" colspan="6" class="py-12 text-center text-zinc-500">{{ $search !== '' || $statusFilter !== '' || $mechanicFilter !== '' || $sourceFilter !== '' ? 'Tidak ada servis yang cocok. Ubah pencarian atau filter.' : ($managesWorkshop ? 'Belum ada servis. Terima motor walk-in untuk memulai.' : 'Belum ada servis yang ditugaskan kepada Anda.') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $orders->links() }}
    @if ($managesWorkshop)
        <livewire:walk-in-intake />
    @endif
</section>
