<?php

namespace App\Livewire;

use App\Actions\ManageStaff;
use App\Enums\Role;
use App\Enums\ServiceStatus;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Staf dan mekanik')]
class Mechanics extends Component
{
    use WithPagination;

    public string $search = '';

    public function boot(): void
    {
        $this->actor();
    }

    private function actor(bool $owner = false): User
    {
        $actor = User::find(auth()->id());
        Gate::allowIf($actor && ($owner ? $actor->role === Role::Owner : $actor->role->managesWorkshop()));

        return $actor;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function create(): void
    {
        $this->actor(true);
        $this->dispatch('open-staff-create')->to(StaffEditor::class);
    }

    public function edit(int $id): void
    {
        $this->actor(true);
        $target = User::query()->whereIn('role', ['owner', 'admin', 'mechanic'])->findOrFail($id);
        $this->dispatch('open-staff-edit', id: $target->id)->to(StaffEditor::class);
    }

    #[On('staff-updated')]
    public function refreshStaff(): void
    {
        $this->actor();
        $this->resetPage();
        session()->flash('status', 'Data staf disimpan.');
    }

    public function deactivate(int $id, ManageStaff $staff): void
    {
        $actor = $this->actor(true);
        $this->resetValidation();
        $staff->deactivate($actor, User::findOrFail($id));
        $this->resetPage();
        session()->flash('status', 'Staf dinonaktifkan. Riwayat tetap tersimpan.');
    }

    public function render(): View
    {
        $owner = $this->actor()->role === Role::Owner;
        $users = User::query()->select($owner ? ['id', 'name', 'role', 'email'] : ['id', 'name', 'role'])
            ->whereIn('role', $owner ? ['owner', 'admin', 'mechanic'] : ['mechanic'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.mb_substr($this->search, 0, 120).'%'))
            ->addSelect(['active_orders' => ServiceOrder::query()->selectRaw('COUNT(*)')->whereColumn('mechanic_id', 'users.id')
                ->whereNotIn('status', [ServiceStatus::Delivered, ServiceStatus::Cancelled])])
            ->orderBy('name')->orderBy('id')->paginate(15);

        return view('livewire.mechanics', ['staff' => $users, 'owner' => $owner]);
    }
}
