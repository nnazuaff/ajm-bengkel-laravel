<section class="mx-auto w-full max-w-5xl space-y-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div><flux:heading size="xl" level="1">Bon · editor</flux:heading><flux:text class="mt-1">Draf, transaksi terkunci, pembayaran, dan koreksi pembukuan.</flux:text></div>
        <flux:button href="{{ route('receipts.index') }}" wire:navigate>Kembali ke daftar bon</flux:button>
    </header>
    @if(session('status'))<p role="status" class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if($errors->any())<div role="alert" class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:bg-red-950 dark:text-red-200"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="space-y-5 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-wrap justify-between gap-3"><flux:heading level="2">{{ $selected?->receipt_number ?? 'Bon baru' }} @if($selected) · {{ $selected->status->label() }}@endif</flux:heading></div>
        @if(!$selected || $selected->status === \App\Enums\ReceiptStatus::Draft)
        <form wire:submit="save" class="space-y-5">
            @if(!$selected)
            <div class="grid gap-4 md:grid-cols-2">
                <flux:select wire:model.live="serviceOrderId" label="Sumber transaksi"><option value="">Penjualan langsung (tanpa servis)</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->service_number }} · {{ $service->customer->name }}</option>@endforeach</flux:select>
                @if(!$serviceOrderId)<flux:select wire:model="customerId" label="Pelanggan"><option value="">Umum</option>@foreach($customers as $customer)<option value="{{ $customer->id }}">{{ $customer->name }} · {{ $customer->phone }}</option>@endforeach</flux:select>@endif
            </div>
            @endif
            @if($serviceOrderId)<flux:text>Pekerjaan selesai dan spare part aktif diambil otomatis dari servis saat finalisasi. Tambahan manual ditulis di bawah.</flux:text>@endif
            @if(!$serviceOrderId)
            <div class="grid gap-4 md:grid-cols-2"><flux:input wire:model.live.debounce.300ms="productSearch" label="Cari produk" placeholder="Nama atau SKU" type="search" maxlength="120"/><flux:select wire:model.live="categoryId" label="Kategori produk"><option value="">Semua kategori</option>@foreach($categories as $category)<option value="{{ $category->id }}">{{ $category->name }}</option>@endforeach</flux:select></div>
            @endif
            <div class="space-y-3">
                @foreach($items as $index => $line)
                @continue(!is_array($line) || !in_array($line['type'] ?? null, ['product', 'custom'], true))
                <div wire:key="receipt-line-{{ $editingId ?? 'new' }}-{{ $index }}" class="grid items-end gap-3 rounded-lg border border-zinc-200 p-3 md:grid-cols-12 dark:border-zinc-700">
                    <div class="md:col-span-5">
                        @if($line['type'] === 'product')
                        <flux:select wire:model="items.{{ $index }}.inventory_item_id" label="Produk · harga dari server" required><option value="">Pilih produk</option>@foreach($products as $product)<option value="{{ $product->id }}">{{ $product->sku }} · {{ $product->name }} · Rp {{ $product->selling_price }} · Stok {{ $product->current_stock }}</option>@endforeach</flux:select>
                        @else<flux:input wire:model="items.{{ $index }}.description" label="Biaya tambahan" maxlength="255" required/>@endif
                    </div>
                    <div class="md:col-span-2"><flux:input wire:model="items.{{ $index }}.quantity" type="number" min="1" max="1000000" label="Jumlah" required/></div>
                    <div class="md:col-span-3">@if($line['type'] === 'custom')<flux:input wire:model="items.{{ $index }}.unit_price" label="Harga satuan (Rp)" inputmode="decimal" required/>@else<flux:text>Harga dihitung saat simpan/final.</flux:text>@endif</div>
                    <div class="md:col-span-2"><flux:button size="sm" wire:click="removeItem({{ $index }})" aria-label="Hapus baris {{ $index+1 }}">Hapus baris</flux:button></div>
                </div>
                @endforeach
                @if(!$items)<p class="text-sm text-zinc-500">Belum ada baris tambahan. Pilih produk atau tambah biaya manual.</p>@endif
            </div>
            <div class="flex flex-wrap gap-3">@if(!$serviceOrderId)<flux:button wire:click="addItem('product')">Tambah produk</flux:button>@endif<flux:button wire:click="addItem('custom')">Tambah biaya</flux:button></div>
            <div class="grid gap-4 md:grid-cols-2"><flux:input wire:model="discount" label="Diskon total (Rp)" inputmode="decimal" required/><flux:textarea wire:model="notes" label="Catatan" rows="2" maxlength="5000"/></div>
            <div class="flex flex-wrap justify-end gap-3"><flux:button type="submit" variant="primary" wire:loading.attr="disabled">Simpan draf</flux:button>
                @if($selected)<flux:button wire:click="finalize" wire:confirm="Finalisasi bon ini? Perubahan yang belum disimpan tidak digunakan. Harga produk terbaru digunakan; stok penjualan langsung dikurangi. Bon menjadi terkunci." wire:loading.attr="disabled">Finalisasi bon</flux:button>@endif
            </div>
        </form>
        @endif
        @if($selected)
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><caption class="sr-only">Baris bon tersimpan</caption><thead><tr class="border-b border-zinc-200 dark:border-zinc-700"><th class="py-3">Deskripsi</th><th class="p-3 text-right">Jumlah</th><th class="p-3 text-right">Harga (Rp)</th><th class="p-3 text-right">Total (Rp)</th></tr></thead><tbody>@foreach($selected->items as $line)<tr class="border-b border-zinc-100 dark:border-zinc-800"><td class="py-3">{{ $line->description }}</td><td class="p-3 text-right">{{ $line->quantity }}</td><td class="p-3 text-right tabular-nums">{{ $line->unit_price }}</td><td class="p-3 text-right tabular-nums">{{ $line->total }}</td></tr>@endforeach</tbody></table></div>
        <dl class="grid gap-3 rounded-lg bg-zinc-50 p-4 text-sm sm:grid-cols-4 dark:bg-zinc-800"><div><dt>Subtotal</dt><dd class="font-semibold">Rp {{ $selected->subtotal }}</dd></div><div><dt>Diskon</dt><dd>Rp {{ $selected->discount }}</dd></div><div><dt>Total</dt><dd class="font-semibold">Rp {{ $selected->grand_total }}</dd></div><div><dt>{{ $selected->payment_status->label() }}</dt><dd>Sisa Rp {{ $selected->status->value === 'voided' ? '0.00' : bcsub($selected->grand_total,$paid,2) }}</dd></div></dl>
        @if($selected->status !== \App\Enums\ReceiptStatus::Draft)<div class="flex flex-wrap gap-3"><flux:button href="{{ route('receipts.show',$selected) }}" target="_blank">Lihat / cetak bon</flux:button><flux:button href="{{ route('receipts.image',$selected) }}">Unduh PNG</flux:button></div>@endif
        @if($selected->status === \App\Enums\ReceiptStatus::Final)
        <form wire:submit="pay" class="space-y-4 border-t border-zinc-200 pt-5 dark:border-zinc-700"><flux:heading level="3">Catat pembayaran</flux:heading><div class="grid gap-4 md:grid-cols-4"><flux:input wire:model="amount" label="Jumlah (Rp)" inputmode="decimal" required/><flux:select wire:model="method" label="Metode">@foreach(\App\Enums\PaymentMethod::cases() as $option)<option value="{{ $option->value }}">{{ $option->label() }}</option>@endforeach</flux:select><flux:input wire:model="paidAt" label="Tanggal pembayaran" type="datetime-local" required/><flux:input wire:model="reference" label="Referensi (opsional)" maxlength="255"/></div><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:confirm="Catat pembayaran ini? Pastikan jumlah dan metode benar.">Simpan pembayaran</flux:button></form>
        @endif
        @if($selected->payments->isNotEmpty())<div class="space-y-3"><flux:heading level="3">Riwayat pembayaran</flux:heading>@foreach($selected->payments as $payment)<div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 p-3 text-sm dark:border-zinc-700"><p>{{ $payment->paid_at->format('d/m/Y H:i') }} · {{ $payment->method->label() }} · Rp {{ $payment->amount }} · {{ $payment->reference }} @if($payment->reversed_at)<strong>Dibalik: {{ $payment->reversal_reason }}</strong>@endif</p>@can('reversePayment',$selected)@if(!$payment->reversed_at)<flux:button size="sm" wire:click="reversePayment({{ $payment->id }})" wire:confirm="Balik pembayaran dalam pembukuan? Tidak ada transfer refund bank otomatis. Isi alasan koreksi di bawah." wire:loading.attr="disabled">Balik pembayaran</flux:button>@endif@endcan</div>@endforeach</div>@endif
        @can('void',$selected)
        @if($selected->status !== \App\Enums\ReceiptStatus::Voided)<div class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-700"><flux:textarea wire:model="reason" label="Alasan pembatalan / pembalikan (wajib)" maxlength="2000" rows="2"/><p class="text-sm text-amber-700 dark:text-amber-300">Koreksi pembukuan tidak mengembalikan uang melalui bank. Pembatalan bon mengembalikan stok dan membalik seluruh pembayaran aktif.</p><flux:button variant="danger" wire:click="void" wire:confirm="Batalkan bon dan kembalikan stok? Seluruh pembayaran aktif akan dibalik dalam pembukuan, bukan refund bank. Tindakan tercatat dalam audit." wire:loading.attr="disabled">Batalkan bon</flux:button></div>@else<flux:text>Alasan pembatalan: {{ $selected->void_reason }}</flux:text>@endif
        @endcan
        @endif
    </div>
</section>
