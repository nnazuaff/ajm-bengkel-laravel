<section class="mx-auto w-full max-w-7xl space-y-6">
    <header><flux:heading size="xl" level="1">Log audit</flux:heading><flux:text class="mt-1">Riwayat tindakan operasional. Data sensitif dan konteks mentah tidak ditampilkan.</flux:text></header>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <flux:select wire:model.live="action" label="Tindakan"><option value="">Semua tindakan</option>@foreach ($actions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</flux:select>
        <flux:select wire:model.live="actorId" label="Pelaku"><option value="">Semua pelaku</option>@foreach ($actors as $actor)<option value="{{ $actor->id }}">{{ $actor->name }}{{ $actor->deleted_at ? ' (arsip)' : '' }}</option>@endforeach</flux:select>
        <flux:input wire:model.live="from" label="Dari tanggal" type="date" />
        <flux:input wire:model.live="to" label="Sampai tanggal" type="date" />
    </div>
    <p role="status" class="text-sm text-zinc-500">{{ $logs->total() }} aktivitas <span wire:loading>· Memuat…</span></p>
    <div class="overflow-x-auto rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900"><table class="workshop-responsive-table w-full text-left text-sm" role="table"><caption class="sr-only">Riwayat audit bengkel</caption><thead role="rowgroup" class="border-b border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"><tr role="row"><th role="columnheader" scope="col" class="px-5 py-3">Waktu</th><th role="columnheader" scope="col" class="px-5 py-3">Pelaku</th><th role="columnheader" scope="col" class="px-5 py-3">Tindakan</th><th role="columnheader" scope="col" class="px-5 py-3">Referensi</th></tr></thead><tbody role="rowgroup" class="divide-y divide-zinc-100 dark:divide-zinc-800">
    @forelse ($logs as $log)<tr role="row" wire:key="audit-{{ $log->id }}"><td role="cell" data-label="Waktu" class="px-5 py-4 whitespace-nowrap">{{ $log->created_at?->format('d/m/Y H:i') }}</td><td role="cell" data-label="Pelaku" class="px-5 py-4">{{ $log->actor?->name ?? 'Sistem' }}{{ $log->actor?->deleted_at ? ' (arsip)' : '' }}</td><td role="cell" data-label="Tindakan" class="px-5 py-4">{{ $actions[$log->action] }}</td><td role="cell" data-label="Referensi" class="px-5 py-4 tabular-nums">#{{ $log->entity_id }}</td></tr>@empty<tr role="row"><td role="cell" colspan="4" class="px-5 py-12 text-center text-zinc-500">Tidak ada aktivitas yang cocok dengan filter.</td></tr>@endforelse
    </tbody></table></div>{{ $logs->links() }}
</section>
