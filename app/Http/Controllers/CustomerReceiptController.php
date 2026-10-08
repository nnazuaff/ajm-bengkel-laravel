<?php

namespace App\Http\Controllers;

use App\Enums\ReceiptStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\User;
use App\Support\ReceiptImage;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class CustomerReceiptController extends Controller
{
    private function authorizeReceipt(Receipt $receipt): void
    {
        $actor = User::query()->find(Auth::id());
        abort_unless($actor && $actor->role === Role::Customer && $actor->canUseCustomerAccess(), 403);
        Gate::authorize('customer-portal');
        abort_unless($receipt->status !== ReceiptStatus::Draft && $receipt->customer_id
            && Customer::query()->whereKey($receipt->customer_id)->where('user_id', $actor->id)->exists(), 404);
    }

    public function show(Receipt $receipt): Response
    {
        $this->authorizeReceipt($receipt);
        $receipt->load(['items', 'payments']);
        $paid = '0.00';
        foreach ($receipt->payments as $payment) {
            if (! $payment->reversed_at) {
                $paid = bcadd($paid, $payment->amount, 2);
            }
        }

        return response()->view('receipts.show', ['receipt' => $receipt, 'paid' => $paid, 'customerReceipt' => true])
            ->withHeaders(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function image(Receipt $receipt): Response
    {
        $this->authorizeReceipt($receipt);
        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', $receipt->receipt_number).'.png';

        return response(app(ReceiptImage::class)->render($receipt), 200, [
            'Content-Type' => 'image/png', 'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store',
        ]);
    }
}
