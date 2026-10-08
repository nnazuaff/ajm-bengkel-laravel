<?php

namespace App\Livewire;

use App\Actions\SaveServiceJob;
use App\Enums\Role;
use App\Enums\ServiceJobStatus;
use App\Enums\ServiceStatus;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ServiceJobs extends Component
{
    #[Locked]
    public int $serviceOrderId = 0;

    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    /** @var array<string, mixed> */
    public array $form = [];

    public function boot(): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        if ($this->serviceOrderId !== 0) {
            $this->order();
        }
    }

    public function mount(int $serviceOrderId): void
    {
        $this->authorize('viewAny', ServiceOrder::class);
        $this->serviceOrderId = $serviceOrderId;
        $this->order();
    }

    public function create(): void
    {
        $order = $this->order();
        $this->authorize('create', [ServiceJob::class, $order]);
        $this->resetValidation();
        $this->editingId = null;
        $this->form = ['name' => '', 'description' => '', 'notes' => '', 'status' => 'pending'];
        if ($this->actor()->role->managesWorkshop()) {
            $this->form['labor_price'] = '0.00';
            $this->form['mechanic_id'] = (string) ($order->mechanic_id ?? '');
        }
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $order = $this->order();
        $job = $this->job($order, $id);
        $this->authorize('update', $job);
        $this->resetValidation();
        $this->editingId = $job->id;
        $this->form = ['name' => $job->name, 'description' => $job->description ?? '', 'notes' => $job->notes ?? '', 'status' => $job->status->value];
        if ($this->actor()->role->managesWorkshop()) {
            $this->form['labor_price'] = $job->labor_price;
            $this->form['mechanic_id'] = (string) ($job->mechanic_id ?? '');
        }
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->order();
        $this->reset('editingId', 'form', 'showForm');
        $this->resetValidation();
    }

    public function save(): void
    {
        $order = $this->order();
        $job = $this->editingId === null ? null : $this->job($order, $this->editingId);
        $this->resetValidation();
        $input = $this->form;
        foreach (['description', 'notes', 'mechanic_id'] as $field) {
            if (($input[$field] ?? null) === '') {
                $input[$field] = null;
            }
        }
        try {
            app(SaveServiceJob::class)->save($this->actor(), $order, $input, $job);
        } catch (ValidationException $exception) {
            $this->formErrors($exception);

            return;
        }
        $this->closeForm();
        session()->flash('jobStatus', 'Pekerjaan berhasil disimpan.');
    }

    public function cancel(int $id): void
    {
        $order = $this->order();
        $job = $this->job($order, $id);
        $this->resetValidation();
        try {
            app(SaveServiceJob::class)->save($this->actor(), $order, ['status' => ServiceJobStatus::Cancelled->value], $job);
        } catch (ValidationException $exception) {
            $this->formErrors($exception);

            return;
        }
        $this->closeForm();
        session()->flash('jobStatus', 'Pekerjaan dibatalkan; riwayat tetap tersimpan.');
    }

    private function actor(): User
    {
        $actor = Auth::user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }

    private function order(): ServiceOrder
    {
        $this->authorize('viewAny', ServiceOrder::class);
        $order = ServiceOrder::query()->findOrFail($this->serviceOrderId);
        $this->authorize('view', $order);

        return $order;
    }

    private function job(ServiceOrder $order, int $id): ServiceJob
    {
        $job = ServiceJob::query()->findOrFail($id);
        if ($job->service_order_id !== $order->id) {
            throw new AuthorizationException('Pekerjaan tidak berasal dari order ini.');
        }
        $job->setRelation('serviceOrder', $order);
        $this->authorize('view', $job);

        return $job;
    }

    private function formErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $key => $messages) {
            foreach ($messages as $message) {
                $this->addError('form.'.$key, $message);
            }
        }
    }

    public function formatMoney(string $decimal): string
    {
        if (! is_numeric($decimal) || ! preg_match('/\A[0-9]+(?:\.[0-9]+)?\z/', $decimal)) {
            throw new \InvalidArgumentException('Nilai jasa harus berupa desimal non-negatif.');
        }

        return 'Rp '.str_replace('.', ',', bcadd($decimal, '0', 2));
    }

    public function render(): View
    {
        $order = $this->order();
        $actor = $this->actor();
        $managesWorkshop = $actor->role->managesWorkshop();
        $query = ServiceJob::query()->where('service_order_id', $order->id)
            ->when(! $managesWorkshop, fn (Builder $query) => $query->where(fn (Builder $query) => $query->whereNull('mechanic_id')->orWhere('mechanic_id', $actor->id)));
        $jobs = (clone $query)->with('mechanic')->orderBy('id')->get();
        $subtotal = (clone $query)->where('status', '!=', ServiceJobStatus::Cancelled->value)
            ->selectRaw('CAST(COALESCE(SUM(labor_price), 0) AS CHAR) AS labor_subtotal')->first();
        $currentStatus = ServiceJobStatus::Pending;
        if ($this->editingId !== null) {
            $currentStatus = $this->job($order, $this->editingId)->status;
        }

        return view('livewire.service-jobs', [
            'jobs' => $jobs,
            'managesWorkshop' => $managesWorkshop,
            'locked' => in_array($order->status, [ServiceStatus::Completed, ServiceStatus::ReadyForPickup, ServiceStatus::Delivered, ServiceStatus::Cancelled], true),
            'statuses' => array_values(array_filter([$currentStatus, ...$currentStatus->transitions()], fn (ServiceJobStatus $status) => $managesWorkshop || $status !== ServiceJobStatus::Cancelled)),
            'mechanics' => $managesWorkshop ? User::query()->where('role', Role::Mechanic->value)->orderBy('name')->get(['id', 'name']) : collect(),
            'laborSubtotal' => $this->formatMoney((string) ($subtotal?->getAttribute('labor_subtotal') ?? '0')),
        ]);
    }
}
