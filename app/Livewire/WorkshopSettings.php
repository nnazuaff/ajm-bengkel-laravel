<?php

namespace App\Livewire;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkshopSetting;
use App\Support\ReceiptImage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts::app')]
#[Title('Identitas bengkel')]
class WorkshopSettings extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $logo = null;

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $receipt_footer = '';

    public function mount(): void
    {
        $this->owner();
        $this->fill(WorkshopSetting::current()->only(['name', 'phone', 'address', 'receipt_footer']));
    }

    public function save(): void
    {
        $this->owner();
        $data = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[+0-9() .-]*$/'],
            'address' => ['nullable', 'string', 'max:2000'],
            'receipt_footer' => ['nullable', 'string', 'max:1000'],
            'logo' => $this->logoRules(),
        ]);
        unset($data['logo']);
        $newPath = null;
        try {
            if ($this->logo) {
                $newPath = 'logos/'.$this->logo->hashName();
                $stream = $this->logo->readStream();
                try {
                    if (! is_resource($stream) || ! Storage::disk('public')->put($newPath, $stream)) {
                        throw ValidationException::withMessages(['logo' => 'Logo gagal disimpan. Silakan coba lagi.']);
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $data['logo_path'] = $newPath;
            }
            DB::transaction(function () use ($data): void {
                $actor = $this->owner(true);
                $setting = WorkshopSetting::query()->whereKey(1)->lockForUpdate()->firstOrFail();
                $setting->update($data);
                AuditLog::create([
                    'actor_id' => $actor->id, 'action' => 'workshop.settings_updated',
                    'entity_type' => WorkshopSetting::class, 'entity_id' => 1,
                    'context' => ['fields' => array_keys($data)],
                ]);
            }, attempts: 5);
        } catch (\Throwable $exception) {
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }
            throw $exception;
        }
        // Historical receipts retain the old public logo; never delete it on replacement.
        $this->reset('logo');
        session()->flash('status', 'Identitas bengkel disimpan. Bon yang sudah difinalisasi tetap menggunakan identitas saat transaksi.');
    }

    /** @return list<string> */
    private function logoRules(): array
    {
        return ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=1024,max_height=1024'];
    }

    private function owner(bool $lock = false): User
    {
        $actor = User::query()->whereKey(auth()->id())->when($lock, fn ($query) => $query->lockForUpdate())->first();
        abort_unless($actor && $actor->role === Role::Owner, 403);

        return $actor;
    }

    public function render(): View
    {
        $this->owner();

        $path = ReceiptImage::safeLogoPath(WorkshopSetting::current()->logo_path);

        return view('livewire.workshop-settings', [
            'logoUrl' => $path ? Storage::disk('public')->url($path) : null,
            'previewUrl' => $this->logo && Validator::make(['logo' => $this->logo], ['logo' => $this->logoRules()])->passes() ? $this->logo->temporaryUrl() : null,
        ]);
    }
}
