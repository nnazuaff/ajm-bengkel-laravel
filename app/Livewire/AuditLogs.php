<?php

namespace App\Livewire;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts::app')]
#[Title('Log audit')]
class AuditLogs extends Component
{
    use WithPagination;

    public const ACTIONS = [
        'staff.created' => 'Staf dibuat', 'user.created' => 'Akun staf dibuat',
        'staff.updated' => 'Staf diperbarui', 'staff.deactivated' => 'Staf dinonaktifkan',
        'workshop.settings_updated' => 'Identitas bengkel diperbarui',
        'service.received' => 'Servis diterima', 'service.updated' => 'Servis diperbarui',
        'service_job.created' => 'Pekerjaan dibuat', 'service_job.updated' => 'Pekerjaan diperbarui',
        'booking.created' => 'Booking dibuat', 'booking.confirmed' => 'Booking dikonfirmasi',
        'booking.rejected' => 'Booking ditolak', 'booking.cancelled' => 'Booking dibatalkan',
        'booking.rescheduled' => 'Booking dijadwal ulang', 'booking.arrived' => 'Pelanggan tiba',
        'booking.converted' => 'Booking diterima sebagai servis',
        'inventory.created' => 'Barang dibuat', 'inventory.updated' => 'Barang diperbarui',
        'inventory.archived' => 'Barang diarsipkan', 'inventory_category.created' => 'Kategori barang dibuat',
        'inventory.price_changed' => 'Harga barang diperbarui', 'inventory.stock_changed' => 'Stok berubah',
        'stock_movement.created' => 'Pergerakan stok dicatat',
        'service_part.used' => 'Part servis digunakan', 'service_part.returned' => 'Part servis dikembalikan',
        'service_documentation.uploaded' => 'Foto servis ditambahkan', 'service_documentation.removed' => 'Foto servis diarsipkan',
        'receipt.created' => 'Bon dibuat', 'receipt.draft_updated' => 'Draf bon diperbarui',
        'receipt.finalized' => 'Bon difinalisasi', 'receipt.voided' => 'Bon dibatalkan',
        'payment.created' => 'Pembayaran dicatat', 'payment.reversed' => 'Pembayaran dibalik',
    ];

    public string $action = '';

    public string $actorId = '';

    public string $from = '';

    public string $to = '';

    public function mount(): void
    {
        $this->authorizeRead();
    }

    private function authorizeRead(): void
    {
        $actor = User::find(auth()->id());
        Gate::allowIf($actor && $actor->role->managesWorkshop());
    }

    public function updated(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $this->authorizeRead();
        $this->validate([
            'action' => ['nullable', 'string', 'max:100'], 'actorId' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $logs = AuditLog::query()->select(['id', 'actor_id', 'action', 'entity_id', 'created_at'])
            ->whereIn('action', array_keys(self::ACTIONS))->with('actor:id,name,deleted_at')
            ->when($this->action !== '', fn ($q) => $q->where('action', $this->action))
            ->when($this->actorId !== '', fn ($q) => $q->where('actor_id', $this->actorId))
            ->when($this->from !== '', fn ($q) => $q->where('created_at', '>=', $this->from.' 00:00:00'))
            ->when($this->to !== '', fn ($q) => $q->where('created_at', '<=', $this->to.' 23:59:59'))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(20);

        return view('livewire.audit-logs', [
            'logs' => $logs, 'actions' => self::ACTIONS,
            'actors' => User::withTrashed()->select('id', 'name', 'deleted_at')
                ->whereIn('id', AuditLog::query()->select('actor_id')->whereIn('action', array_keys(self::ACTIONS)))
                ->orderBy('name')->get(),
        ]);
    }
}
