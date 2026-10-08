<?php

namespace App\Livewire;

use App\Actions\UpdateServiceOrder;
use App\Enums\Role;
use App\Enums\ServiceSource;
use App\Enums\ServiceStatus;
use App\Models\ServiceOrder;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Servis')]
class Services extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $statusFilter = '';

    public string $mechanicFilter = '';

    public string $sourceFilter = '';

    #[Locked]
    public bool $detailPage = false;

    #[Locked]
    public ?int $selectedOrderId = null;

    /** @var array<string, mixed> */
    public array $detail = ['status' => '', 'diagnosis' => '', 'notes' => ''];

    public function boot(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
    }

    public function mount(?int $serviceOrder = null): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        if ($serviceOrder !== null) {
            $this->detailPage = true;
            $this->openOrder($serviceOrder);
        }
    }

    public function updated(string $property): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        if (in_array($property, ['search', 'statusFilter', 'mechanicFilter', 'sourceFilter'], true)) {
            $this->resetPage();
        }
    }

    #[On('walk-in-received')]
    public function refreshOrders(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        $this->resetPage();
    }

    public function openOrder(int $id): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        $order = ServiceOrder::query()->findOrFail($id);
        $this->authorize('view', $order);
        $this->selectedOrderId = $order->id;
        $this->fillDetail($order);
        $this->resetValidation();
    }

    public function closeOrder(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        $this->reset('selectedOrderId', 'detail');
        $this->resetValidation();
    }

    public function saveOrder(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        abort_if($this->selectedOrderId === null, 404);
        $order = ServiceOrder::query()->findOrFail($this->selectedOrderId);
        $this->authorize('update', $order);
        /** @var User $actor */
        $actor = Auth::user();
        $input = [
            'status' => $this->detail['status'] ?? '',
            'diagnosis' => ($this->detail['diagnosis'] ?? '') === '' ? null : $this->detail['diagnosis'],
            'notes' => ($this->detail['notes'] ?? '') === '' ? null : $this->detail['notes'],
        ];
        if ($actor->role->managesWorkshop() || array_key_exists('mechanic_id', $this->detail)) {
            $input['mechanic_id'] = ($this->detail['mechanic_id'] ?? '') === '' ? null : $this->detail['mechanic_id'];
        }
        $this->resetValidation();

        try {
            $order = app(UpdateServiceOrder::class)->update($actor, $order, $input);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                foreach ($messages as $message) {
                    $this->addError('detail.'.$key, $message);
                }
            }

            return;
        }

        $this->fillDetail($order);
        session()->flash('status', 'Servis '.$order->service_number.' berhasil diperbarui.');
    }

    private function fillDetail(ServiceOrder $order): void
    {
        $this->detail = [
            'status' => $order->status->value,
            'diagnosis' => $order->diagnosis ?? '',
            'notes' => $order->notes ?? '',
        ];
        /** @var User $actor */
        $actor = Auth::user();
        if ($actor->role->managesWorkshop()) {
            $this->detail['mechanic_id'] = $order->mechanic_id ?? '';
        }
    }

    public function render(): View
    {
        $this->authorize('viewAny', ServiceOrder::class);
        /** @var User $actor */
        $actor = Auth::user();
        $managesWorkshop = $actor->role->managesWorkshop();
        $search = Str::squish(Str::substr($this->search, 0, 120));
        $mechanics = $managesWorkshop ? User::query()->where('role', Role::Mechanic)->orderBy('name')->get(['id', 'name']) : collect();

        if ($this->detailPage) {
            abort_if($this->selectedOrderId === null, 404);
            $selectedOrder = ServiceOrder::query()->with(['customer', 'vehicle', 'mechanic', 'receiver'])->findOrFail($this->selectedOrderId);
            $this->authorize('view', $selectedOrder);
            $allowedStatuses = array_values(array_filter(
                [$selectedOrder->status, ...$selectedOrder->status->transitions()],
                fn (ServiceStatus $status) => $managesWorkshop || $status === $selectedOrder->status
                    || in_array($status, [ServiceStatus::Inspection, ServiceStatus::InProgress, ServiceStatus::WaitingPart, ServiceStatus::Completed], true),
            ));

            return view('livewire.service-detail', compact('selectedOrder', 'allowedStatuses', 'managesWorkshop', 'mechanics'));
        }

        $orders = ServiceOrder::query()->with(['customer', 'vehicle', 'mechanic'])
            ->when(! $managesWorkshop, fn (Builder $query) => $query->where('mechanic_id', $actor->id))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereRaw("LOWER(service_number) LIKE ? ESCAPE '!'", [WorkshopInput::like(Str::lower($search))])
                ->orWhereHas('vehicle', fn (Builder $query) => $query->whereRaw("license_plate LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::plate($search))]))
                ->orWhereHas('customer', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                    ->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [WorkshopInput::like(Str::lower($search))])
                    ->orWhereRaw("phone LIKE ? ESCAPE '!'", [WorkshopInput::like(WorkshopInput::phone($search))])))))
            ->when($this->statusFilter !== '', fn (Builder $query) => $query->where('status', $this->statusFilter))
            ->when($this->sourceFilter !== '', fn (Builder $query) => $query->where('source', $this->sourceFilter))
            ->when($managesWorkshop && $this->mechanicFilter !== '', fn (Builder $query) => $query->where('mechanic_id', $this->mechanicFilter))
            ->orderByDesc('received_at')->orderByDesc('id')->paginate(15);

        return view('livewire.services', [
            'orders' => $orders,
            'statuses' => ServiceStatus::cases(),
            'sources' => ServiceSource::cases(),
            'managesWorkshop' => $managesWorkshop,
            'mechanics' => $mechanics,
        ]);
    }
}
