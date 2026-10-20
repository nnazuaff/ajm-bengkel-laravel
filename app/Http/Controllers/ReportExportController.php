<?php

namespace App\Http\Controllers;

use App\Livewire\Reports;
use App\Models\User;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;

class ReportExportController extends Controller
{
    private const HEADINGS = ['Tanggal', 'Penerimaan (Rp)', 'Pembayaran Dibatalkan (Rp)', 'Penerimaan Bersih (Rp)', 'Pembayaran Aktif (Rp)', 'Servis Selesai', 'Stok Masuk', 'Stok Keluar'];

    public function __invoke(Request $request): Response
    {
        $actor = User::find($request->user()?->id);
        abort_unless($actor && $actor->role->managesWorkshop(), 403);
        $data = $request->validate(Reports::dateRules());
        $format = $request->query('format', 'csv');
        abort_unless(is_string($format) && in_array($format, ['csv', 'xlsx', 'pdf'], true), 404);
        $days = Reports::daily($data['from'], $data['to']);

        if ($format === 'pdf') {
            $pdf = new Dompdf;
            $pdf->setPaper('a4', 'landscape');
            $pdf->loadHtml(view('reports.pdf', ['days' => $days, 'from' => $data['from'], 'to' => $data['to']])->render());
            $pdf->render();

            return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="payments-'.$data['from'].'-'.$data['to'].'.pdf"', 'X-Content-Type-Options' => 'nosniff']);
        }

        if ($format === 'xlsx') {
            return response()->streamDownload(function () use ($days): void {
                $book = new Spreadsheet;
                try {
                    $sheet = $book->getActiveSheet();
                    $sheet->setTitle('Laporan harian');
                    foreach (self::HEADINGS as $column => $heading) {
                        $sheet->setCellValueExplicit([$column + 1, 1], $heading, DataType::TYPE_STRING);
                    }
                    foreach ($days as $index => $day) {
                        foreach (array_values($day) as $column => $value) {
                            $sheet->setCellValueExplicit([$column + 1, $index + 2], (string) $value, DataType::TYPE_STRING);
                        }
                    }
                    $sheet->getStyle('A1:H1')->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '334155']],
                    ]);
                    $sheet->getRowDimension(1)->setRowHeight(28);
                    foreach (range('A', 'H') as $column) {
                        $sheet->getColumnDimension($column)->setWidth($column === 'A' ? 16 : 30);
                    }
                    $sheet->setAutoFilter('A1:H'.($days->count() + 1));
                    $sheet->freezePane('A2');

                    (new Xlsx($book))->save('php://output');
                } finally {
                    $book->disconnectWorksheets();
                }
            }, 'payments-'.$data['from'].'-'.$data['to'].'.xlsx', ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'X-Content-Type-Options' => 'nosniff']);
        }

        return response()->streamDownload(function () use ($days): void {
            $file = fopen('php://output', 'w');
            if ($file === false) {
                throw new \RuntimeException('CSV output unavailable.');
            }
            fputcsv($file, self::HEADINGS, escape: '');
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
