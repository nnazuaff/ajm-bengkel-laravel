<?php

namespace App\Livewire;

use App\Enums\ReceiptStatus;
use App\Enums\Role;
use App\Models\Customer;
use App\Models\Receipt;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Portal pelanggan')]
class CustomerPortal extends Component
{
    use AuthorizesRequests, WithPagination;

    #[Locked]
    public ?int $selectedVehicle = null;

    #[Locked]
    public ?int $selectedOrder = null;

    public function boot(): void
    {
        $this->checkAccess();
    }

    public function mount(): void
    {
        $this->checkAccess();
    }

    private function checkAccess(): void
    {
        $actor = User::query()->find(Auth::id());
        abort_unless($actor && $actor->role === Role::Customer && $actor->canUseCustomerAccess(), 403);
        $this->authorize('customer-portal');
    }

    private function customer(): ?Customer
    {
        $this->checkAccess();

        return Customer::query()->where('user_id', Auth::id())->first();
    }

    private function ownVehicle(int $id, ?Customer $customer): Vehicle
    {
        abort_unless($customer !== null, 404);

        return Vehicle::withTrashed()->where('customer_id', $customer->id)->findOrFail($id);
    }

    private function ownOrder(int $id, ?Customer $customer): ServiceOrder
    {
        abort_unless($customer !== null, 404);
        $order = ServiceOrder::query()->where('customer_id', $customer->id)->findOrFail($id);
        $this->ownVehicle($order->vehicle_id, $customer);

        return $order;
    }

    public function selectVehicle(?int $id = null): void
    {
        $customer = $this->customer();
        if ($id !== null) {
            $this->ownVehicle($id, $customer);
        }
        $this->selectedVehicle = $id;
        $this->selectedOrder = null;
        $this->resetPage('servicesPage');
    }

    public function selectOrder(int $id): void
    {
        $order = $this->ownOrder($id, $this->customer());
        $this->selectedVehicle = $order->vehicle_id;
        $this->selectedOrder = $order->id;
    }

    public function render(): View
    {
        $customer = $this->customer();
        $customerId = $customer !== null ? $customer->id : 0;
        $vehicle = $this->selectedVehicle !== null ? $this->ownVehicle($this->selectedVehicle, $customer) : null;
        $order = $this->selectedOrder !== null ? $this->ownOrder($this->selectedOrder, $customer) : null;
        if ($order) {
            abort_unless($order->vehicle_id === $this->selectedVehicle, 404);
            $order->load(['mechanic', 'jobs.mechanic', 'parts', 'documentation.serviceJob',
                'receipt' => fn ($query) => $query->where('customer_id', $customer->id)->where('status', '!=', ReceiptStatus::Draft->value),
                'receipt.payments']);
        }
        $orders = ServiceOrder::query()->where('customer_id', $customerId)
            ->whereHas('vehicle', fn ($query) => $query->where('customer_id', $customerId))
            ->when($vehicle, fn ($query) => $query->where('vehicle_id', $vehicle->id))
            ->with(['vehicle', 'mechanic'])->orderByDesc('received_at')->orderByDesc('id')
            ->paginate(10, pageName: 'servicesPage');
        $receipts = Receipt::query()->where('customer_id', $customerId)
            ->where('status', '!=', ReceiptStatus::Draft->value)->orderByDesc('transaction_date')->orderByDesc('id')
            ->paginate(10, pageName: 'receiptsPage');

        return view('livewire.customer-portal', [
            'customer' => $customer,
            'vehicles' => $customer?->vehicles()->withTrashed()->orderBy('license_plate')->get() ?? collect(),
            'vehicle' => $vehicle, 'orders' => $orders, 'order' => $order, 'receipts' => $receipts,
        ]);
    }
}
