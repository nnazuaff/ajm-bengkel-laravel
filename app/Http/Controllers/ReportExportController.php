<?php

namespace App\Http\Controllers;

use App\Livewire\Reports;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request): StreamedResponse
    {
        $actor = User::find($request->user()?->id);
        abort_unless($actor && $actor->role->managesWorkshop(), 403);
        $data = $request->validate(Reports::dateRules());
        $days = Reports::daily($data['from'], $data['to']);

        return response()->streamDownload(function () use ($days): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('CSV output unavailable.');
            }
            fputcsv($file, ['tanggal', 'penerimaan', 'pembalikan', 'bersih', 'aktif', 'servis_selesai', 'stok_masuk', 'stok_keluar'], escape: '');
            foreach ($days as $day) {
                fputcsv($file, array_map(self::csvCell(...), array_values($day)), escape: '');
            }
            fclose($file);
        }, 'payments-'.$data['from'].'-'.$data['to'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'X-Content-Type-Options' => 'nosniff']);
    }

    public static function csvCell(string|int $value): string
    {
        $value = (string) $value;
        if (preg_match('/^[\s]*[=+@-]/u', $value) && ! preg_match('/^-?[0-9]+(?:\.[0-9]{1,2})?$/D', $value)) {
            return "'".$value;
        }

        return $value;
    }
}
