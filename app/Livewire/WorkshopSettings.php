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

    public ?TemporaryUploadedFile $horizontalLogo = null;

    public ?TemporaryUploadedFile $favicon = null;

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
            'horizontalLogo' => $this->horizontalLogoRules(),
            'favicon' => $this->faviconRules(),
        ]);
        unset($data['logo'], $data['horizontalLogo'], $data['favicon']);
        $newPaths = [];
        try {
            foreach (['logo' => 'logo_path', 'horizontalLogo' => 'horizontal_logo_path', 'favicon' => 'favicon_path'] as $field => $column) {
                $upload = $this->{$field};
                if (! $upload) {
                    continue;
                }
                $newPath = 'logos/'.$upload->hashName();
                $newPaths[] = $newPath;
                $stream = $upload->readStream();
                try {
                    if (! is_resource($stream) || ! Storage::disk('public')->put($newPath, $stream)) {
                        throw ValidationException::withMessages([$field => 'Logo gagal disimpan. Silakan coba lagi.']);
                    }
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
                $data[$column] = $newPath;
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
            if ($newPaths !== []) {
                Storage::disk('public')->delete($newPaths);
            }
            throw $exception;
        }
        // Historical receipts retain the old public logo; never delete it on replacement.
        $this->reset('logo', 'horizontalLogo', 'favicon');
        session()->flash('status', 'Identitas bengkel disimpan. Bon yang sudah difinalisasi tetap menggunakan identitas saat transaksi.');
    }

    /** @return list<string> */
    private function logoRules(): array
    {
        return ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=1024,max_height=1024'];
    }

    /** @return list<string> */
    private function horizontalLogoRules(): array
    {
        return ['nullable', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:width=1600,height=560'];
    }

    /** @return list<string> */
    private function faviconRules(): array
    {
        return ['nullable', 'image', 'mimetypes:image/png', 'max:1024', 'dimensions:min_width=32,min_height=32,max_width=512,max_height=512,ratio=1/1'];
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
            'faviconUrl' => WorkshopSetting::current()->faviconUrl(),
            'faviconPreviewUrl' => $this->favicon && Validator::make(['favicon' => $this->favicon], ['favicon' => $this->faviconRules()])->passes() ? $this->favicon->temporaryUrl() : null,
            'horizontalLogoUrl' => WorkshopSetting::current()->horizontalLogoUrl(),
            'horizontalPreviewUrl' => $this->horizontalLogo && Validator::make(['logo' => $this->horizontalLogo], ['logo' => $this->horizontalLogoRules()])->passes() ? $this->horizontalLogo->temporaryUrl() : null,
            'logoUrl' => $path ? Storage::disk('public')->url($path) : null,
            'previewUrl' => $this->logo && Validator::make(['logo' => $this->logo], ['logo' => $this->logoRules()])->passes() ? $this->logo->temporaryUrl() : null,
        ]);
    }
}
