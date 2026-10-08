<?php

namespace App\Models;

use App\Enums\DocumentationCategory;
use Carbon\CarbonImmutable;
use Database\Factories\ServiceDocumentationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $service_order_id
 * @property int|null $service_job_id
 * @property int $uploaded_by
 * @property string $disk
 * @property string $path
 * @property DocumentationCategory $category
 * @property string|null $caption
 * @property CarbonImmutable|null $created_at
 */
#[Fillable(['service_order_id', 'service_job_id', 'disk', 'path', 'category', 'caption', 'uploaded_by'])]
#[Hidden(['disk', 'path'])]
class ServiceDocumentation extends Model
{
    /** @use HasFactory<ServiceDocumentationFactory> */
    use HasFactory, SoftDeletes;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['category' => DocumentationCategory::class, 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime', 'deleted_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ServiceOrder, $this> */
    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    /** @return BelongsTo<ServiceJob, $this> */
    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by')->withTrashed();
    }
}
