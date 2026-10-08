<?php

namespace App\Livewire;

use App\Actions\ManageServicePhoto;
use App\Enums\DocumentationCategory;
use App\Enums\ServiceStatus;
use App\Models\ServiceDocumentation;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class ServicePhotos extends Component
{
    use AuthorizesRequests, WithFileUploads;

    #[Locked]
    public ?int $serviceOrderId = null;

    /** @var UploadedFile|array<UploadedFile>|null */
    public UploadedFile|array|null $photo = null;

    public string $category = 'before';

    public string $caption = '';

    public string $notice = '';

    public string $serviceJobId = '';

    #[Locked]
    public ?int $pendingRemovalId = null;

    public function boot(): void
    {
        $this->authorize('viewAny', ServiceDocumentation::class);
        if ($this->serviceOrderId !== null) {
            $this->order();
        }
    }

    public function mount(int $serviceOrderId): void
    {
        $this->serviceOrderId = $serviceOrderId;
        $this->order();
    }

    public function updatedPhoto(): void
    {
        $order = $this->order();
        $this->authorize('create', [ServiceDocumentation::class, $order]);
        $this->validate(['photo' => ManageServicePhoto::photoRules()]);
    }

    public function savePhoto(): void
    {
        $order = $this->order();
        $this->authorize('create', [ServiceDocumentation::class, $order]);
        $this->validate(['photo' => ['required', 'file']]);
        /** @var UploadedFile $file */
        $file = $this->photo;
        /** @var User $actor */
        $actor = Auth::user();
        app(ManageServicePhoto::class)->upload($actor, $order, $file, [
            'category' => $this->category, 'caption' => $this->caption === '' ? null : $this->caption,
            'service_job_id' => $this->serviceJobId === '' ? null : $this->serviceJobId,
        ]);
        $this->reset('photo', 'caption', 'serviceJobId');
        $this->notice = 'Foto berhasil disimpan.';
        $this->resetValidation();
        $this->dispatch('service-photos-updated');
    }

    public function requestRemoval(int $id): void
    {
        $order = $this->order();
        $photo = ServiceDocumentation::query()->where('service_order_id', $order->id)->findOrFail($id);
        $this->authorize('delete', $photo);
        if (in_array($order->status, [ServiceStatus::Delivered, ServiceStatus::Cancelled], true)) {
            $this->addError('photo', 'Order sudah ditutup. Dokumentasi tidak dapat diubah.');

            return;
        }
        $this->resetValidation();
        $this->pendingRemovalId = $photo->id;
    }

    public function cancelRemoval(): void
    {
        $this->order();
        $this->reset('pendingRemovalId');
        $this->resetValidation();
    }

    public function remove(): void
    {
        $order = $this->order();
        if ($this->pendingRemovalId === null) {
            $this->addError('photo', 'Pilih foto dan konfirmasi penghapusan terlebih dahulu.');

            return;
        }
        $photo = ServiceDocumentation::query()->where('service_order_id', $order->id)->findOrFail($this->pendingRemovalId);
        /** @var User $actor */
        $actor = Auth::user();
        app(ManageServicePhoto::class)->remove($actor, $photo);
        $this->notice = 'Foto dihapus dari galeri. Bukti asli tetap tersimpan secara privat.';
        $this->reset('pendingRemovalId');
        $this->resetValidation();
        $this->dispatch('service-photos-updated');
    }

    private function order(): ServiceOrder
    {
        abort_if($this->serviceOrderId === null, 404);
        $order = ServiceOrder::query()->findOrFail($this->serviceOrderId);
        $this->authorize('viewOrder', [ServiceDocumentation::class, $order]);

        return $order;
    }

    public function render(): View
    {
        $order = $this->order();

        return view('livewire.service-photos', [
            'photos' => ServiceDocumentation::query()->where('service_order_id', $order->id)->with(['serviceJob', 'uploader'])->latest('id')->get(),
            'jobs' => $order->jobs()->orderBy('name')->get(),
            'categories' => DocumentationCategory::cases(),
            'previewUrl' => $this->photo instanceof TemporaryUploadedFile && Validator::make(['photo' => $this->photo], ['photo' => ManageServicePhoto::photoRules()])->passes() ? $this->photo->temporaryUrl() : null,
            'readOnly' => in_array($order->status, [ServiceStatus::Delivered, ServiceStatus::Cancelled], true),
        ]);
    }
}
