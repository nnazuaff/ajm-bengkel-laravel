<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View|RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (! $user->role->isStaff()) {
            return redirect()->route('portal');
        }

        $orders = ServiceOrder::query()
            ->when($user->role === Role::Mechanic, fn ($query) => $query->where('mechanic_id', $user->id));
        $counts = (clone $orders)->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status');
        $metrics = [];
        foreach (['waiting', 'inspection', 'in_progress', 'waiting_part', 'ready_for_pickup'] as $status) {
            $metrics[$status] = (int) ($counts[$status] ?? 0);
        }
        $metrics['received_today'] = (clone $orders)->whereDate('received_at', today())->count();
        $metrics['completed_today'] = (clone $orders)->whereDate('completed_at', today())->count();

        if ($user->role->managesWorkshop()) {
            $metrics['bookings_today'] = Booking::query()->whereDate('booking_date', today())
                ->whereNotIn('status', ['cancelled', 'rejected', 'converted_to_service'])->count();
            $metrics['low_stock'] = InventoryItem::query()->where('is_active', true)->where('current_stock', '>', 0)->whereColumn('current_stock', '<=', 'minimum_stock')->count();
            $metrics['out_of_stock'] = InventoryItem::query()->where('is_active', true)->where('current_stock', 0)->count();
            $metrics['unpaid_receipts'] = Receipt::query()->where('status', 'final')->where('payment_status', '!=', 'paid')->count();
            $metrics['revenue_today'] = '0.00';
            foreach (Payment::query()->whereNull('reversed_at')->whereDate('paid_at', today())->select('amount')->cursor() as $payment) {
                $metrics['revenue_today'] = bcadd($metrics['revenue_today'], $payment->amount, 2);
            }
        }

        return view('dashboard', [
            'metrics' => $metrics,
            'latestActivities' => $user->role->managesWorkshop() ? AuditLog::query()->with('actor')->latest('id')->limit(8)->get() : collect(),
            'latestOrders' => $orders->with(['vehicle', 'customer', 'mechanic'])->latest('received_at')->limit(6)->get(),
        ]);
    }
}
