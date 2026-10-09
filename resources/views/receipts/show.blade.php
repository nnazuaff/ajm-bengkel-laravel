<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
    <title>{{ $receipt->receipt_number }} · Bon</title>
    <style>
        *{box-sizing:border-box}body{margin:0;padding:24px;background:#f4f4f5;color:#18181b;font:15px/1.6 system-ui,sans-serif}main{max-width:760px;margin:auto;background:white;padding:36px;border:1px solid #e4e4e7;border-radius:12px}h1,h2,p{margin:0 0 10px}h1{font-size:26px}h2{font-size:20px}small,.muted{color:#52525b}nav{max-width:760px;margin:0 auto 18px;display:flex;gap:16px;flex-wrap:wrap}a,button{color:inherit;font:inherit}button{cursor:pointer;padding:6px 14px}table{width:100%;border-collapse:collapse;margin:24px 0;font-variant-numeric:tabular-nums}td,th{padding:12px 6px;border-bottom:1px solid #e4e4e7;text-align:left;overflow-wrap:anywhere}td:first-child{max-width:320px}.right{text-align:right}dl{margin-left:auto;max-width:380px}dl div{display:flex;justify-content:space-between;gap:12px;padding:6px 0}dt,dd{margin:0}.total{font-size:20px;font-weight:700;border-top:2px solid #18181b;margin-top:10px}footer{border-top:1px solid #e4e4e7;margin-top:24px;padding-top:20px;white-space:pre-wrap;overflow-wrap:anywhere}.status{border:1px solid #a1a1aa;padding:6px 12px;display:inline-block} .scroll{overflow-x:auto}@media(max-width:600px){body{padding:12px}main{padding:18px}td,th{padding:10px 4px;font-size:12px}}@media print{body{background:white;padding:0}nav{display:none}main{border:0;border-radius:0;max-width:none;padding:0}tr{break-inside:avoid}thead{display:table-header-group}a{display:none}}
    </style>
    @vite('resources/css/responsive-tables.css')
</head>
<body>
<nav aria-label="Tindakan bon"><a href="{{ isset($customerReceipt) ? route('portal') : route('receipts.index',['receipt_id'=>$receipt->id]) }}">Kembali ke detail bon</a><a href="{{ route(isset($customerReceipt) ? 'customer.receipts.image' : 'receipts.image',$receipt) }}">Unduh PNG</a><button type="button" onclick="window.print()">Cetak</button></nav>
<main>
    @php($logoPath = \App\Support\ReceiptImage::safeLogoPath($receipt->workshop_snapshot['logo_path'] ?? null))
    <header>@if($logoPath)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($logoPath) }}" alt="Logo bengkel" width="120" height="120" style="object-fit:contain;background:white;margin-bottom:16px" />@endif<h1>{{ $receipt->workshop_snapshot['name'] ?? 'AJM Bengkel' }}</h1><p class="muted">{{ $receipt->workshop_snapshot['address'] ?? '' }}<br>{{ $receipt->workshop_snapshot['phone'] ?? '' }}</p></header>
    <h2>{{ $receipt->receipt_number }}</h2><p>{{ $receipt->transaction_date->format('d/m/Y H:i') }}</p>
    <p>Pelanggan: <strong>{{ $receipt->customer_snapshot['name'] ?? 'Umum' }}</strong>@if(!empty($receipt->customer_snapshot['phone'])) · {{ $receipt->customer_snapshot['phone'] }}@endif</p>
    @if($receipt->vehicle_snapshot)<p>Motor: {{ $receipt->vehicle_snapshot['license_plate'] ?? '' }} · {{ $receipt->vehicle_snapshot['brand'] ?? '' }} {{ $receipt->vehicle_snapshot['model'] ?? '' }}</p>@endif
    <p class="status">{{ $receipt->status->label() }} · {{ $receipt->payment_status->label() }}</p>
    <div class="scroll"><table class="workshop-responsive-table" role="table"><caption class="muted">Rincian transaksi (Rp)</caption><thead role="rowgroup"><tr role="row"><th role="columnheader" scope="col">Deskripsi</th><th role="columnheader" scope="col" class="right">Jumlah</th><th role="columnheader" scope="col" class="right">Harga satuan</th><th role="columnheader" scope="col" class="right">Total</th></tr></thead><tbody role="rowgroup">@foreach($receipt->items as $line)<tr role="row"><td role="cell" data-label="Deskripsi">{{ $line->description }}</td><td role="cell" data-label="Jumlah" class="right">{{ $line->quantity }}</td><td role="cell" data-label="Harga satuan" class="right">{{ $line->unit_price }}</td><td role="cell" data-label="Total" class="right">{{ $line->total }}</td></tr>@endforeach</tbody></table></div>
    <dl><div><dt>Subtotal</dt><dd>Rp {{ $receipt->subtotal }}</dd></div><div><dt>Diskon</dt><dd>Rp {{ $receipt->discount }}</dd></div><div class="total"><dt>Total</dt><dd>Rp {{ $receipt->grand_total }}</dd></div><div><dt>Dibayar aktif</dt><dd>Rp {{ $paid }}</dd></div><div><dt>Sisa tagihan</dt><dd>Rp {{ $receipt->status->value === 'voided' ? '0.00' : bcsub($receipt->grand_total,$paid,2) }}</dd></div></dl>
    @if($receipt->payments->isNotEmpty())<h2>Pembayaran</h2>@foreach($receipt->payments as $payment)<p>{{ $payment->paid_at->format('d/m/Y H:i') }} · {{ $payment->method->label() }} · Rp {{ $payment->amount }}@if($payment->reversed_at) · <strong>Dibalik dalam pembukuan</strong>@endif @if($payment->reference)<br><small>Referensi: {{ $payment->reference }}</small>@endif</p>@endforeach @endif
    @if($receipt->notes)<p>Catatan: {{ $receipt->notes }}</p>@endif
    @if($receipt->void_reason)<p><strong>Alasan pembatalan:</strong> {{ $receipt->void_reason }}</p>@endif
    <footer>{{ $receipt->workshop_snapshot['receipt_footer'] ?? '' }}</footer>
</main>
</body>
</html>
