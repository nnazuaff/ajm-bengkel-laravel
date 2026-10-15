<?php

namespace App\Http\Controllers;

use App\Enums\ReceiptStatus;
use App\Models\Receipt;
use App\Support\ReceiptImage;
use Dompdf\Dompdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    public function show(Receipt $receipt): View
    {
        Gate::authorize('view', $receipt);
        abort_if($receipt->status === ReceiptStatus::Draft, 409, 'Bon masih draf. Finalisasi sebelum mencetak.');
        $receipt->load(['items', 'payments']);
        $paid = '0.00';
        foreach ($receipt->payments as $payment) {
            if (! $payment->reversed_at) {
                $paid = bcadd($paid, $payment->amount, 2);
            }
        }

        return view('receipts.show', compact('receipt', 'paid'));
    }

    public function pdf(Receipt $receipt): Response
    {
        Gate::authorize('view', $receipt);
        abort_if($receipt->status === ReceiptStatus::Draft, 409, 'Bon masih draf.');
        $receipt->load(['items', 'payments']);
        $paid = '0.00';
        foreach ($receipt->payments as $payment) {
            if (! $payment->reversed_at) {
                $paid = bcadd($paid, $payment->amount, 2);
            }
        }
        $pdf = new Dompdf;
        $pdf->setPaper('a4');
        $pdf->loadHtml(view('receipts.pdf', compact('receipt', 'paid'))->render());
        $pdf->render();
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $receipt->receipt_number).'.pdf';

        return response($pdf->output(), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function image(Receipt $receipt): Response
    {
        Gate::authorize('view', $receipt);
        abort_if($receipt->status === ReceiptStatus::Draft, 409, 'Bon masih draf.');
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $receipt->receipt_number).'.png';

        return response(app(ReceiptImage::class)->render($receipt), 200, ['Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="'.$filename.'"', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
