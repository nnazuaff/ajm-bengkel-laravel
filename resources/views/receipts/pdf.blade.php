<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }} · Bon</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #18181b; font-size: 11px; }
        h1 { font-size: 20px; margin-bottom: 4px; }
        h2 { font-size: 15px; margin: 22px 0 8px; }
        p { margin: 5px 0; }
        .muted { color: #52525b; }
        table { border-collapse: collapse; width: 100%; margin: 20px 0; }
        th, td { padding: 8px 4px; border-bottom: 1px solid #d4d4d8; text-align: left; }
        th.right, td.right { text-align: right; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .summary { width: 48%; margin-left: auto; }
        .summary td { border: 0; }
        .total { font-weight: bold; border-top: 2px solid #18181b; }
        footer { margin-top: 24px; border-top: 1px solid #d4d4d8; padding-top: 12px; white-space: pre-wrap; }
    </style>
</head>
<body>
    <h1>{{ $receipt->workshop_snapshot['name'] ?? 'AJM Bengkel' }}</h1>
    <p class="muted">{{ $receipt->workshop_snapshot['address'] ?? '' }} · {{ $receipt->workshop_snapshot['phone'] ?? '' }}</p>
    <h2>{{ $receipt->receipt_number }}</h2>
    <p>{{ $receipt->transaction_date->format('d/m/Y H:i') }} · {{ $receipt->status->label() }} · {{ $receipt->payment_status->label() }}</p>
    <p>Pelanggan: {{ $receipt->customer_snapshot['name'] ?? 'Umum' }}</p>
    @if($receipt->vehicle_snapshot)<p>Motor: {{ $receipt->vehicle_snapshot['license_plate'] ?? '' }} · {{ $receipt->vehicle_snapshot['brand'] ?? '' }} {{ $receipt->vehicle_snapshot['model'] ?? '' }}</p>@endif
    <table><thead><tr><th>Deskripsi</th><th class="right">Jumlah</th><th class="right">Harga satuan</th><th class="right">Total</th></tr></thead><tbody>
        @foreach($receipt->items as $line)<tr><td>{{ $line->description }}</td><td class="right">{{ $line->quantity }}</td><td class="right">{{ \App\Support\Rupiah::format($line->unit_price) }}</td><td class="right">{{ \App\Support\Rupiah::format($line->total) }}</td></tr>@endforeach
    </tbody></table>
    <table class="summary"><tr><td>Subtotal</td><td class="right">{{ \App\Support\Rupiah::format($receipt->subtotal) }}</td></tr><tr><td>Diskon</td><td class="right">{{ \App\Support\Rupiah::format($receipt->discount) }}</td></tr><tr class="total"><td>Total</td><td class="right">{{ \App\Support\Rupiah::format($receipt->grand_total) }}</td></tr><tr><td>Dibayar aktif</td><td class="right">{{ \App\Support\Rupiah::format($paid) }}</td></tr><tr><td>Sisa tagihan</td><td class="right">{{ \App\Support\Rupiah::format($receipt->status->value === 'voided' ? '0.00' : bcsub($receipt->grand_total, $paid, 2)) }}</td></tr></table>
    @foreach($receipt->payments as $payment)<p>Pembayaran: {{ $payment->paid_at->format('d/m/Y H:i') }} · {{ $payment->method->label() }} · {{ \App\Support\Rupiah::format($payment->amount) }}{{ $payment->reversed_at ? ' (dibalik)' : '' }}</p>@endforeach
    @if($receipt->void_reason)<p>Alasan pembatalan: {{ $receipt->void_reason }}</p>@endif
    @if($receipt->notes)<p>Catatan: {{ $receipt->notes }}</p>@endif
    <footer>{{ $receipt->workshop_snapshot['receipt_footer'] ?? '' }}</footer>
</body>
</html>
