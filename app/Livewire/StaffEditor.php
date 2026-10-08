<?php

namespace App\Livewire;

use App\Actions\ManageStaff;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class StaffEditor extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public bool $showForm = false;

    public string $name = '';

    public string $email = '';

    public string $role = 'mechanic';

    public string $password = '';

    public string $password_confirmation = '';

    public function boot(): void
    {
        $this->actor();
    }

    private function actor(): User
    {
        $actor = User::find(auth()->id());
        Gate::allowIf($actor && $actor->role === Role::Owner);

        return $actor;
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'showForm', 'name', 'email', 'role', 'password', 'password_confirmation');
        $this->resetValidation();
    }

    #[On('open-staff-create')]
    public function create(): void
    {
        $this->actor();
        $this->resetForm();
        $this->showForm = true;
    }

    public function cancel(): void
    {
        $this->actor();
        $id = $this->editingId;
        $this->resetForm();
        $this->dispatch('staff-editor-closed', id: $id);
    }

    public function updatedShowForm(bool $open): void
    {
        if (! $open) {
            $this->cancel();
        }
    }

    #[On('open-staff-edit')]
    public function edit(int $id): void
    {
        $this->actor();
        $target = User::query()->whereIn('role', ['owner', 'admin', 'mechanic'])->findOrFail($id);
        $this->resetForm();
        $this->editingId = $target->id;
        $this->name = $target->name;
        $this->role = $target->role->value;
        $this->showForm = true;
    }

    public function save(ManageStaff $staff): void
    {
        $actor = $this->actor();
        $input = $this->only(['name', 'email', 'role', 'password', 'password_confirmation']);
        try {
            $this->editingId === null ? $staff->create($actor, $input)
                : $staff->save($actor, User::findOrFail($this->editingId), $input);
        } finally {
            $this->reset('password', 'password_confirmation');
        }
        $id = $this->editingId;
        $this->resetForm();
        $this->dispatch('staff-editor-closed', id: $id);
        $this->dispatch('staff-updated');
    }

    public function render(): View
    {
        if ($this->showForm) {
            $this->actor();
        }

        return view('livewire.staff-editor');
    }
}
