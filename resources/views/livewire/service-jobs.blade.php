<section class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-800" aria-labelledby="jobs-heading-{{ $serviceOrderId }}">
    <header class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <flux:heading level="3" id="jobs-heading-{{ $serviceOrderId }}">Pekerjaan yang dilakukan</flux:heading>
            <flux:text class="mt-1">Catat tindakan nyata, terpisah dari keluhan dan diagnosis.</flux:text>
        </div>
        @if (! $locked)
            <flux:button type="button" size="sm" icon="plus" wire:click="create" wire:loading.attr="disabled">Tambah pekerjaan</flux:button>
        @endif
    </header>
    @if (session('jobStatus'))
        <p role="status" class="rounded-lg bg-emerald-50 p-3 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('jobStatus') }}</p>
    @endif
    <flux:error name="form.status" />
    @if ($locked)
        <p role="status" class="text-sm text-zinc-500">Pekerjaan terkunci karena order sudah selesai atau ditutup. Riwayat tetap dapat dilihat.</p>
    @elseif ($showForm)
        <form wire:submit="save" class="space-y-4 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700" aria-labelledby="job-form-heading-{{ $serviceOrderId }}" wire:key="job-form-{{ $serviceOrderId }}-{{ $editingId ?? 'new' }}" x-init="$nextTick(() => $el.querySelector('input')?.focus())">
            <flux:heading level="4" id="job-form-heading-{{ $serviceOrderId }}">{{ $editingId ? 'Ubah pekerjaan' : 'Pekerjaan baru' }}</flux:heading>
            <div class="grid gap-4 md:grid-cols-2">
                <flux:input wire:model="form.name" label="Nama pekerjaan" maxlength="120" required placeholder="Contoh: bersihkan rem belakang" />
                <flux:field>
                    <flux:label for="job-status-{{ $serviceOrderId }}">Status pekerjaan</flux:label>
                    <select id="job-status-{{ $serviceOrderId }}" wire:model="form.status" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                        @foreach ($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach
                    </select>
                </flux:field>
                <div class="md:col-span-2"><flux:textarea wire:model="form.description" label="Rincian tindakan (opsional)" rows="2" maxlength="5000" /></div>
                @if ($managesWorkshop)
                    <flux:input wire:model="form.labor_price" label="Harga jasa (Rp)" type="text" inputmode="decimal" required maxlength="15" placeholder="25000.00" description="Gunakan titik untuk desimal, maksimal 2 angka; tanpa pemisah ribuan." />
                    <flux:field>
                        <flux:label for="job-mechanic-{{ $serviceOrderId }}">Mekanik pekerjaan</flux:label>
                        <select id="job-mechanic-{{ $serviceOrderId }}" wire:model="form.mechanic_id" class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-zinc-700 dark:border-zinc-700 dark:bg-zinc-900">
                            <option value="">Belum ditugaskan</option>
                            @if (($form['mechanic_id'] ?? '') !== '' && ! $mechanics->contains('id', $form['mechanic_id']))<option value="{{ $form['mechanic_id'] }}">Mekanik tidak aktif; pilih ulang</option>@endif
                            @foreach ($mechanics as $mechanic)<option value="{{ $mechanic->id }}">{{ $mechanic->name }}</option>@endforeach
                        </select>
                        <flux:error name="form.mechanic_id" />
                    </flux:field>
                @else
                    <p class="text-sm text-zinc-500 md:col-span-2">Pekerjaan baru ditugaskan kepada Anda. Harga jasa dan pembatalan dikelola admin.</p>
                    <flux:error name="form.labor_price" />
                    <flux:error name="form.mechanic_id" />
                @endif
                <div class="md:col-span-2"><flux:textarea wire:model="form.notes" label="Catatan pekerjaan (opsional)" rows="2" maxlength="5000" /></div>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-3">
                <flux:button type="button" wire:click="closeForm">Tutup form</flux:button>
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="save">Simpan pekerjaan</flux:button>
            </div>
        </form>
    @endif
    <p role="status" wire:loading wire:target="create,edit,save,cancel,closeForm" class="text-sm text-zinc-500">Memproses pekerjaan…</p>
    <ul class="divide-y divide-zinc-200 dark:divide-zinc-800">
        @forelse ($jobs as $job)
            <li wire:key="service-job-{{ $job->id }}" class="space-y-2 py-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <h4 class="break-words text-sm font-semibold">{{ $job->name }}</h4>
                        <p class="mt-1 text-xs text-zinc-500">{{ $job->mechanic?->name ?? 'Belum ditugaskan' }} · {{ $this->formatMoney($job->labor_price) }}</p>
                    </div>
                    <flux:badge :color="$job->status->color()" size="sm">{{ $job->status->label() }}</flux:badge>
                </div>
                @if ($job->description)<p class="whitespace-pre-line break-words text-sm">{{ $job->description }}</p>@endif
                @if ($job->notes)<p class="whitespace-pre-line break-words text-sm text-zinc-500">Catatan: {{ $job->notes }}</p>@endif
                @if (! $locked && in_array($job->status, [\App\Enums\ServiceJobStatus::Pending, \App\Enums\ServiceJobStatus::InProgress], true))
                    <div class="flex flex-wrap gap-2">
                        <flux:button type="button" size="sm" variant="ghost" wire:click="edit({{ $job->id }})" wire:loading.attr="disabled" aria-label="Ubah pekerjaan {{ $job->name }}">Ubah</flux:button>
                        @if ($managesWorkshop)<flux:button type="button" size="sm" variant="ghost" wire:click="cancel({{ $job->id }})" wire:confirm="Batalkan pekerjaan ini? Riwayat tetap tersimpan; harga tidak masuk subtotal." wire:loading.attr="disabled" aria-label="Batalkan pekerjaan {{ $job->name }}">Batalkan</flux:button>@endif
                    </div>
                @endif
            </li>
        @empty
            <li class="py-6 text-center text-sm text-zinc-500">Belum ada pekerjaan{{ $managesWorkshop ? '' : ' yang dapat Anda akses' }}. Catat tindakan saat pengerjaan dimulai.</li>
        @endforelse
    </ul>
    <div class="flex flex-wrap justify-between gap-2 rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
        <span>{{ $managesWorkshop ? 'Subtotal jasa' : 'Subtotal jasa pekerjaan yang dapat Anda akses' }} <span class="text-zinc-500">(tanpa pekerjaan dibatalkan)</span></span>
        <strong class="tabular-nums">{{ $laborSubtotal }}</strong>
    </div>
</section>
