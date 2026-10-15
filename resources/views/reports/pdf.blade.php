<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Laporan harian {{ $from }} – {{ $to }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #18181b; font-size: 10px; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        p { color: #52525b; margin: 0 0 14px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border-bottom: 1px solid #d4d4d8; padding: 7px 5px; text-align: right; }
        th:first-child, td:first-child { text-align: left; }
        th { background: #f4f4f5; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
    </style>
</head>
<body>
    <h1>Laporan harian AJM Bengkel</h1>
    <p>{{ $from }} – {{ $to }} · Penerimaan sesuai tanggal pembayaran; koreksi sesuai tanggal pembalikan. Bukan laporan laba.</p>
    <table>
        <thead><tr><th>Tanggal</th><th>Penerimaan</th><th>Pembalikan</th><th>Bersih</th><th>Aktif</th><th>Servis selesai</th><th>Stok masuk</th><th>Stok keluar</th></tr></thead>
        <tbody>
        @forelse($days as $day)
            <tr>
                <td>{{ $day['date'] }}</td>
                @foreach(['gross', 'reversed', 'net', 'active'] as $key)
                    <td>{{ \App\Support\Rupiah::format($day[$key]) }}</td>
                @endforeach
                <td>{{ $day['services'] }}</td><td>{{ $day['stock_in'] }}</td><td>{{ $day['stock_out'] }}</td>
            </tr>
        @empty
            <tr><td colspan="8" style="text-align:center">Tidak ada aktivitas dalam periode ini.</td></tr>
        @endforelse
        </tbody>
    </table>
</body>
</html>
