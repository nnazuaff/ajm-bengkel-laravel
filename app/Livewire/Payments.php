<?php

namespace App\Livewire;

use App\Models\Payment;
use App\Models\Receipt;
use App\Support\WorkshopInput;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Pembayaran')]
class Payments extends Component
{
    use AuthorizesRequests, WithPagination;

    public string $search = '';

    public string $method = '';

    public string $state = '';

    public string $date = '';

    public function boot(): void
    {
        $this->authorize('viewAny', Receipt::class);
    }

    public function updated(string $name): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorize('viewAny', Receipt::class);
        $term = WorkshopInput::like(mb_substr(trim($this->search), 0, 120));

        return view('livewire.payments', ['payments' => Payment::with(['receipt', 'creator'])->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q->whereRaw("reference LIKE ? ESCAPE '!'", [$term])->orWhereHas('receipt', fn ($q) => $q->whereRaw("receipt_number LIKE ? ESCAPE '!'", [$term]))))->when($this->method !== '', fn ($q) => $q->where('method', $this->method))->when($this->state === 'active', fn ($q) => $q->whereNull('reversed_at'))->when($this->state === 'reversed', fn ($q) => $q->whereNotNull('reversed_at'))->when($this->date !== '', fn ($q) => $q->whereDate('paid_at', $this->date))->orderByDesc('paid_at')->orderByDesc('id')->paginate(20)]);
    }
}
