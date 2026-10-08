<?php

namespace App\Actions;

use App\Enums\DocumentationCategory;
use App\Enums\ServiceStatus;
use App\Models\AuditLog;
use App\Models\ServiceDocumentation;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class ManageServicePhoto
{
    /** @param array<string, mixed> $input */
    public function upload(User $actor, ServiceOrder $order, UploadedFile $file, array $input): ServiceDocumentation
    {
        $path = null;
        $disk = Storage::disk('local');
        try {
            return DB::transaction(function () use ($actor, $order, $file, $input, $disk, &$path): ServiceDocumentation {
                $order = ServiceOrder::query()->lockForUpdate()->findOrFail($order->id);
                Gate::forUser($actor)->authorize('create', [ServiceDocumentation::class, $order]);
                $this->assertWritable($order);
                $data = Validator::make([...$input, 'photo' => $file], [
                    'photo' => self::photoRules(),
                    'category' => ['required', Rule::enum(DocumentationCategory::class)],
                    'caption' => ['nullable', 'string', 'max:1000'],
                    'service_job_id' => ['nullable', 'integer', Rule::exists('service_jobs', 'id')->where('service_order_id', $order->id)],
                ])->validate();
                $path = 'service-documentation/'.$order->id.'/'.bin2hex(random_bytes(16)).'.'.$file->extension();
                $stored = $disk->putFileAs(dirname($path), $file, basename($path), ['visibility' => 'private']);
                if ($stored !== $path || ! $disk->exists($path)) {
                    throw ValidationException::withMessages(['photo' => 'Penyimpanan foto gagal. Coba unggah kembali.']);
                }
                $photo = ServiceDocumentation::create([
                    'service_order_id' => $order->id, 'service_job_id' => $data['service_job_id'] ?? null,
                    'disk' => 'local', 'path' => $path, 'category' => $data['category'],
                    'caption' => $data['caption'] ?? null, 'uploaded_by' => $actor->id,
                ]);
                AuditLog::create(['actor_id' => $actor->id, 'action' => 'service_documentation.uploaded',
                    'entity_type' => ServiceDocumentation::class, 'entity_id' => $photo->id,
                    'context' => ['service_order_id' => $order->id, 'service_job_id' => $photo->service_job_id, 'category' => $photo->category->value]]);

                return $photo;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                $disk->delete($path);
            }
            throw $exception;
        }
    }

    public function remove(User $actor, ServiceDocumentation $photo): void
    {
        DB::transaction(function () use ($actor, $photo): void {
            // Lock parent first, then evidence, matching order lifecycle actions.
            $order = ServiceOrder::query()->lockForUpdate()->findOrFail($photo->service_order_id);
            $current = ServiceDocumentation::query()->where('service_order_id', $order->id)->lockForUpdate()->findOrFail($photo->id);
            $current->setRelation('serviceOrder', $order);
            Gate::forUser($actor)->authorize('delete', $current);
            $this->assertWritable($order);
            $current->delete();
            AuditLog::create(['actor_id' => $actor->id, 'action' => 'service_documentation.removed',
                'entity_type' => ServiceDocumentation::class, 'entity_id' => $current->id,
                'context' => ['service_order_id' => $order->id, 'service_job_id' => $current->service_job_id, 'category' => $current->category->value]]);
        });
    }

    /** @return list<string|\Closure> */
    public static function photoRules(): array
    {
        return ['bail', 'required', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:5120', 'dimensions:max_width=4096,max_height=4096',
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile || ! $value->isValid() || ! in_array((new \finfo(FILEINFO_MIME_TYPE))->file($value->getPathname()), ['image/jpeg', 'image/png', 'image/webp'], true)) {
                    $fail('Foto harus berupa JPG, PNG, atau WebP asli.');
                }
            }];
    }

    private function assertWritable(ServiceOrder $order): void
    {
        if (in_array($order->status, [ServiceStatus::Delivered, ServiceStatus::Cancelled], true)) {
            throw ValidationException::withMessages(['photo' => 'Order sudah ditutup. Dokumentasi tidak dapat diubah.']);
        }
    }
}
