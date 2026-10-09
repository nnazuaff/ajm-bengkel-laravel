<div>
    <flux:modal name="booking-review" wire:model="showForm" :closable="false"
        class="w-[calc(100%-2rem)] max-w-3xl max-h-[calc(100dvh-2rem)] overflow-y-auto" aria-labelledby="detail-heading">
        @if ($showForm && $selectedBooking)
            <article class="space-y-5" wire:key="booking-detail-{{ $selectedBooking->id }}"
                aria-labelledby="detail-heading" x-init="$nextTick(() => $el.querySelector('select, input, button')?.focus())">
                <header class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <flux:heading level="2" id="detail-heading">{{ $selectedBooking->booking_number }}
                        </flux:heading>
                        <p class="mt-1 text-sm text-zinc-500">{{ $selectedBooking->license_plate }} ·
                            {{ $selectedBooking->brand }} {{ $selectedBooking->model }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <flux:badge :color="$selectedBooking->status->color()">{{ $selectedBooking->status->label() }}
                        </flux:badge>
                        <flux:button size="sm" wire:click="closeBooking"
                            aria-label="Tutup detail {{ $selectedBooking->booking_number }}">Tutup</flux:button>
                    </div>
                </header>
                <dl class="grid gap-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-zinc-500">Kontak pemesan</dt>
                        <dd class="mt-1 font-medium">{{ $selectedBooking->name }}</dd>
                        <dd class="mt-1">{{ $selectedBooking->phone }}</dd>
                        <dd class="mt-1 break-all">{{ $selectedBooking->email ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Jadwal (WIB)</dt>
                        <dd class="mt-1">{{ $selectedBooking->booking_date->format('d/m/Y') }} ·
                            {{ substr($selectedBooking->arrival_time, 0, 5) }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Jenis servis / kilometer</dt>
                        <dd class="mt-1">{{ $selectedBooking->service_type }}</dd>
                        <dd class="mt-1">{{ number_format($selectedBooking->current_mileage, 0, ',', '.') }} km ·
                            tahun {{ $selectedBooking->year ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-zinc-500">Akun pengirim</dt>
                        <dd class="mt-1">{{ $selectedBooking->submitter?->name ?? 'Petugas bengkel' }}</dd>
                    </div>
                </dl>
                <div class="rounded-lg bg-zinc-50 p-4 dark:bg-zinc-800">
                    <h3 class="text-sm font-medium">Keluhan pelanggan</h3>
                    <p class="mt-2 whitespace-pre-line break-words text-sm">{{ $selectedBooking->complaint }}</p>
                    @if ($selectedBooking->notes)
                        <p class="mt-3 whitespace-pre-line break-words text-sm text-zinc-500">Catatan:
                            {{ $selectedBooking->notes }}</p>
                    @endif
                </div>
                @if (session('status'))
                    <p role="status" class="text-sm text-emerald-700 dark:text-emerald-300">{{ session('status') }}
                    </p>
                @endif
                @php
                    $inlineErrors = [];
                    if ($selectedBooking->status->transitions() !== []) {
                        $inlineErrors = ['detail.status', 'detail.admin_notes'];
                        if (($detail['status'] ?? '') === 'rescheduled') {
                            $inlineErrors = [...$inlineErrors, 'detail.booking_date', 'detail.arrival_time'];
                        }
                    }
                    if ($selectedBooking->status === \App\Enums\BookingStatus::Arrived) {
                        $inlineErrors = [...$inlineErrors, 'detail.ownership_verified', 'detail.link_account', 'detail.restore_archived', 'detail.userId', 'detail.mechanic_id', 'detail.phone', 'detail.license_plate', 'detail.vehicle_id', 'detail.vehicle.license_plate', 'detail.customer.phone', 'detail.current_mileage'];
                    }
                @endphp
                <x-validation-summary :inline="$inlineErrors" />
                @if ($selectedBooking->status->transitions() !== [])
                    <form wire:submit="saveBooking" class="space-y-5">
                        <div class="grid gap-5 md:grid-cols-3">
                            <flux:field>
                                <flux:label for="booking-status">Status booking</flux:label>
                                <select id="booking-status" wire:model.live="detail.status"
                                    class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                    @foreach ($allowedStatuses as $status)
                                        <option value="{{ $status->value }}">{{ $status->label() }}</option>
                                    @endforeach
                                </select>
                                <flux:error name="detail.status" />
                            </flux:field>
                            @if (($detail['status'] ?? '') === 'rescheduled')
                                <flux:input wire:model="detail.booking_date" label="Tanggal jadwal ulang" type="date"
                                    min="{{ now()->toDateString() }}" max="{{ now()->addDays(90)->toDateString() }}"
                                    required />
                                <flux:input wire:model="detail.arrival_time" label="Jam jadwal ulang (WIB)"
                                    type="time" min="08:00" max="17:00" required />
                            @endif
                            <div class="md:col-span-3">
                                <flux:textarea wire:model="detail.admin_notes"
                                    label="Catatan internal / alasan penolakan atau pembatalan" rows="2"
                                    maxlength="5000" />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <span role="status" wire:loading wire:target="saveBooking"
                                class="text-sm text-zinc-500">Menyimpan…</span>
                            <flux:button type="submit" variant="primary" wire:loading.attr="disabled"
                                wire:target="saveBooking">Simpan perubahan</flux:button>
                        </div>
                    </form>
                @else
                    <p class="text-sm text-zinc-500">Booking telah ditutup. Data permintaan tidak dapat diubah.</p>
                    @if ($selectedBooking->admin_notes)
                        <p class="whitespace-pre-line break-words text-sm">Catatan internal:
                            {{ $selectedBooking->admin_notes }}</p>
                    @endif
                @endif
                @if ($selectedBooking->status === \App\Enums\BookingStatus::Arrived)
                    <form wire:submit="convert" class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-800">
                        <flux:heading level="3">Terima sebagai servis</flux:heading>
                        <p class="text-sm text-zinc-500">Cocokkan motor fisik, plat, dan identitas pemilik. Telepon sama
                            bukan bukti kepemilikan. Master dan order dibuat dalam satu transaksi; data lama tidak
                            ditimpa.</p>
                        @if ($matchingCustomer)
                            <p class="text-sm">Master ditemukan: <strong>{{ $matchingCustomer->name }}</strong> ·
                                {{ $matchingCustomer->phone }} @if ($matchingCustomer->trashed())
                                    <flux:badge color="amber">Pelanggan arsip</flux:badge>
                                @endif
                            </p>
                        @endif
                        @if ($matchingVehicle)
                            <p class="text-sm">Motor ditemukan: <strong>{{ $matchingVehicle->license_plate }}</strong>
                                @if ($matchingVehicle->trashed())
                                    <flux:badge color="amber">Motor arsip</flux:badge>
                                @endif
                            </p>
                        @endif
                        @if ($selectedBooking->submitted_by && $selectedBooking->customer?->user_id !== $selectedBooking->submitted_by)
                            <flux:checkbox wire:model="detail.link_account"
                                label="Konfirmasi akses histori untuk akun pemesan setelah verifikasi identitas" />
                            <p class="text-sm text-zinc-500">Akun ini memperoleh seluruh kendaraan dan histori master
                                pelanggan. Jangan hubungkan akun orang yang hanya mengantar motor. Hubungan akun lain
                                tidak diganti otomatis.</p>
                        @endif
                        @if ($matchingCustomer?->trashed() || $matchingVehicle?->trashed())
                            <flux:checkbox wire:model="detail.restore_archived"
                                label="Pulihkan data arsip pelanggan/motor yang cocok" />
                            <p class="text-sm text-zinc-500">Pemulihan mempertahankan ID dan histori lama; tidak membuat
                                pelanggan duplikat.</p>
                        @endif
                        <flux:checkbox wire:model="detail.ownership_verified"
                            label="Saya sudah memverifikasi identitas, motor, dan hak akses histori pelanggan secara langsung" />

                        @if (!$selectedBooking->submitted_by || $selectedBooking->customer?->user_id === $selectedBooking->submitted_by)<flux:error name="detail.link_account" />@endif
                        @if (!$matchingCustomer?->trashed() && !$matchingVehicle?->trashed())<flux:error name="detail.restore_archived" />@endif
                        <flux:error name="detail.userId" />
                        <flux:field>
                            <flux:label for="booking-mechanic">Mekanik (opsional)</flux:label>
                            <select id="booking-mechanic" wire:model="detail.mechanic_id"
                                class="w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm dark:border-zinc-700 dark:bg-zinc-900">
                                <option value="">Belum ditugaskan</option>
                                @foreach ($mechanics as $mechanic)
                                    <option value="{{ $mechanic->id }}">{{ $mechanic->name }}</option>
                                @endforeach
                            </select>
                            <flux:error name="detail.mechanic_id" />
                        </flux:field>
                        <flux:error name="detail.phone" />
                        <flux:error name="detail.license_plate" />
                        <flux:error name="detail.vehicle_id" />
                        <flux:error name="detail.vehicle.license_plate" />
                        <flux:error name="detail.customer.phone" />
                        <flux:error name="detail.current_mileage" />
                        <div class="flex flex-wrap items-center justify-end gap-3">
                            <span role="status" wire:loading wire:target="convert"
                                class="text-sm text-zinc-500">Menerima servis…</span>
                            <flux:button type="submit" variant="primary"
                                wire:confirm="Terima servis sesuai pilihan? Data arsip yang dipilih dipulihkan. Akun yang dihubungkan dapat mengakses seluruh histori pelanggan. Pastikan kepemilikan benar."
                                wire:loading.attr="disabled" wire:target="convert">Terima servis</flux:button>
                        </div>
                    </form>
                @elseif ($selectedBooking->serviceOrder)
                    <p class="text-sm">Order servis: <a
                            href="{{ route('services.detail', $selectedBooking->serviceOrder->id) }}" wire:navigate
                            class="font-medium underline">{{ $selectedBooking->serviceOrder->service_number }}</a></p>
                @endif
            </article>
        @endif
    </flux:modal>
</div>
