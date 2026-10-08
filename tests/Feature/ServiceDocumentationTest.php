<?php

use App\Actions\ManageServicePhoto;
use App\Enums\DocumentationCategory;
use App\Models\AuditLog;
use App\Models\ServiceDocumentation;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->actor = User::factory()->create(['role' => 'admin']);
    $this->order = ServiceOrder::factory()->create();
});

it('uploads a private raster with authoritative actor random path and safe audit', function () {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order,
        UploadedFile::fake()->image('customer-secret.jpg', 40, 40),
        ['category' => 'before', 'caption' => 'Kondisi awal', 'uploaded_by' => 999, 'disk' => 'public', 'path' => '../secret']);

    expect($photo)->toBeInstanceOf(ServiceDocumentation::class)
        ->and($photo->category)->toBe(DocumentationCategory::Before)
        ->and($photo->uploaded_by)->toBe($this->actor->id)
        ->and($photo->service_order_id)->toBe($this->order->id)
        ->and($photo->disk)->toBe('local')
        ->and($photo->path)->toMatch('/\Aservice-documentation\/\d+\/[a-f0-9]{32}\.jpg\z/')
        ->and($photo->toArray())->not->toHaveKeys(['path', 'disk']);
    Storage::disk('local')->assertExists($photo->path);
    $audit = AuditLog::where('action', 'service_documentation.uploaded')->firstOrFail();
    expect($audit->entity_id)->toBe($photo->id)
        ->and(json_encode($audit->context))->not->toContain('customer-secret', $photo->path, 'Kondisi awal');
});

it('rejects unsafe image content sizes dimensions category caption and cross-order job without writing files', function (string $invalid) {
    $file = UploadedFile::fake()->image('ok.png', 20, 20);
    $input = ['category' => 'evidence'];
    if ($invalid === 'svg') {
        $file = UploadedFile::fake()->createWithContent('attack.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
    }
    if ($invalid === 'spoof') {
        $file = UploadedFile::fake()->create('attack.jpg', 1, 'image/jpeg');
    }
    if ($invalid === 'size') {
        $file = UploadedFile::fake()->image('large.jpg')->size(5121);
    }
    if ($invalid === 'width') {
        $file = UploadedFile::fake()->image('wide.png', 4097, 1);
    }
    if ($invalid === 'height') {
        $file = UploadedFile::fake()->image('tall.png', 1, 4097);
    }
    if ($invalid === 'category') {
        $input['category'] = 'wrong';
    }
    if ($invalid === 'caption') {
        $input['caption'] = str_repeat('a', 1001);
    }
    if ($invalid === 'job') {
        $input['service_job_id'] = ServiceJob::factory()->create()->id;
    }
    expect(fn () => app(ManageServicePhoto::class)->upload($this->actor, $this->order, $file, $input))->toThrow(ValidationException::class);
    expect(ServiceDocumentation::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with(['svg', 'spoof', 'size', 'width', 'height', 'category', 'caption', 'job']);

it('denies upload from customers and unrelated mechanics', function (string $role) {
    $actor = User::factory()->create(['role' => $role]);
    expect(fn () => app(ManageServicePhoto::class)->upload($actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'before']))->toThrow(AuthorizationException::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['customer', 'mechanic']);

it('allows assigned mechanic evidence on completed and ready orders', function (string $status) {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $this->order->update(['mechanic_id' => $mechanic->id, 'status' => $status]);
    $job = ServiceJob::factory()->create(['service_order_id' => $this->order->id]);
    $photo = app(ManageServicePhoto::class)->upload($mechanic, $this->order, UploadedFile::fake()->image('ok.webp'), ['category' => 'after', 'service_job_id' => $job->id]);
    expect($photo->service_job_id)->toBe($job->id)->and($photo->uploaded_by)->toBe($mechanic->id);
})->with(['completed', 'ready_for_pickup']);

it('rejects writes to freshly delivered or cancelled orders despite stale model', function (string $status) {
    ServiceOrder::whereKey($this->order->id)->update(['status' => $status]);
    expect(fn () => app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence']))->toThrow(ValidationException::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with(['delivered', 'cancelled']);

it('rolls back row and cleans file when audit fails', function () {
    AuditLog::creating(function () {
        throw new RuntimeException('audit unavailable');
    });
    try {
        expect(fn () => app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'before']))->toThrow(RuntimeException::class, 'audit unavailable');
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(ServiceDocumentation::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('does not persist when disk returns false or reports a missing stored file', function (string $failure) {
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('putFileAs')->once()->andReturnUsing(fn ($directory, $file, $name) => $failure === 'false' ? false : $directory.'/'.$name);
    $disk->shouldReceive('exists')->zeroOrMoreTimes()->andReturn(false);
    $disk->shouldReceive('delete')->once()->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    expect(fn () => app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence']))->toThrow(ValidationException::class);
    expect(ServiceDocumentation::count())->toBe(0)->and(AuditLog::count())->toBe(0);
})->with(['false', 'missing']);

it('removes logically preserving private evidence and audit without leaking caption', function () {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence', 'caption' => 'secret']);
    app(ManageServicePhoto::class)->remove($this->actor, $photo);
    $this->assertSoftDeleted($photo);
    Storage::disk('local')->assertExists($photo->path);
    $audit = AuditLog::where('action', 'service_documentation.removed')->firstOrFail();
    expect(json_encode($audit->context))->not->toContain($photo->path, 'secret');
});

it('rejects removal on newly closed order and keeps evidence', function (string $status) {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence']);
    $this->order->update(['status' => $status]);
    expect(fn () => app(ManageServicePhoto::class)->remove($this->actor, $photo))->toThrow(ValidationException::class);
    expect($photo->fresh()->trashed())->toBeFalse();
})->with(['delivered', 'cancelled']);

it('rolls back removal on failed audit', function () {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence']);
    AuditLog::creating(function () {
        throw new RuntimeException('audit unavailable');
    });
    try {
        expect(fn () => app(ManageServicePhoto::class)->remove($this->actor, $photo))->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($photo->fresh()->trashed())->toBeFalse();
    Storage::disk('local')->assertExists($photo->path);
});

it('serves inline private images to admin and assigned mechanic with archived lineage', function (string $role) {
    $actor = User::factory()->create(['role' => $role]);
    $this->order->update(['mechanic_id' => $actor->id]);
    $photo = app(ManageServicePhoto::class)->upload($actor, $this->order, UploadedFile::fake()->image('ok.png'), ['category' => 'evidence']);
    $this->order->customer->delete();
    $this->order->vehicle->delete();
    $actor->delete();
    expect($photo->fresh()->uploader->id)->toBe($actor->id);
    $actor->restore();
    $response = $this->actingAs($actor)->get(route('documentation.show', $photo));
    $response->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($response->headers->get('Cache-Control'))->toContain('private', 'no-store');
})->with(['owner', 'admin', 'mechanic']);

it('blocks unrelated mechanic and customer downloads', function (string $role) {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence']);
    $actor = User::factory()->create(['role' => $role]);
    if ($role === 'customer') {
        $this->order->customer->update(['user_id' => $actor->id]);
    }
    $this->actingAs($actor)->get(route('documentation.show', $photo))->assertForbidden();
})->with(['mechanic', 'customer']);

it('requires authentication and verified email for download', function () {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence']);
    $this->get(route('documentation.show', $photo))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create(['role' => 'admin']))->get(route('documentation.show', $photo))->assertRedirect(route('verification.notice'));
});

it('returns 404 for missing deleted or unsafe stored paths', function (string $case) {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.jpg'), ['category' => 'evidence']);
    if ($case === 'missing') {
        Storage::disk('local')->delete($photo->path);
    }
    if ($case === 'deleted') {
        app(ManageServicePhoto::class)->remove($this->actor, $photo);
    }
    if ($case === 'path') {
        $photo->update(['path' => '../secret.jpg']);
    }
    if ($case === 'disk') {
        $photo->update(['disk' => 'public']);
    }
    $this->actingAs($this->actor)->get(route('documentation.show', $photo))->assertNotFound();
})->with(['missing', 'deleted', 'path', 'disk']);

it('cannot bind route traversal as a storage path', function () {
    $this->actingAs($this->actor)->get('/documentation/..%2Fsecret.jpg')->assertNotFound();
});

it('preserves PNG alpha and accepts the inclusive size and dimensions limits', function () {
    $image = imagecreatetruecolor(4096, 1);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 30, 40, 50, 100));
    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    $file = UploadedFile::fake()->createWithContent('alpha.png', $bytes)->size(5120);
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, $file, ['category' => 'other']);
    expect(Storage::disk('local')->get($photo->path))->toBe($bytes);
});

it('cleans stored file on failed database save', function () {
    ServiceDocumentation::creating(function () {
        throw new RuntimeException('database unavailable');
    });
    try {
        expect(fn () => app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.png'), ['category' => 'before']))->toThrow(RuntimeException::class);
    } finally {
        ServiceDocumentation::flushEventListeners();
        ServiceDocumentation::clearBootedModels();
    }
    expect(Storage::disk('local')->allFiles())->toBe([])->and(ServiceDocumentation::count())->toBe(0);
});

it('denies removal from unassigned mechanic or customer preserving evidence', function (string $role) {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.png'), ['category' => 'before']);
    expect(fn () => app(ManageServicePhoto::class)->remove(User::factory()->create(['role' => $role]), $photo))->toThrow(AuthorizationException::class);
    expect($photo->fresh()->trashed())->toBeFalse();
})->with(['mechanic', 'customer']);
