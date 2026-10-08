<?php

use App\Actions\ManageServicePhoto;
use App\Enums\DocumentationCategory;
use App\Livewire\ServicePhotos;
use App\Models\ServiceDocumentation;
use App\Models\ServiceJob;
use App\Models\ServiceOrder;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->actor = User::factory()->create(['role' => 'admin']);
    $this->order = ServiceOrder::factory()->create();
});

it('shows empty gallery and uploads a validated photo through admin form', function () {
    $job = ServiceJob::factory()->create(['service_order_id' => $this->order->id, 'name' => 'Periksa rem']);
    Livewire::actingAs($this->actor)->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id])
        ->assertSee('Belum ada foto dokumentasi.')
        ->assertSee('5 MB')->assertSee('4096')->assertSee('Periksa rem')
        ->set('photo', UploadedFile::fake()->image('rem.png', 30, 30))
        ->set('category', 'process')->set('caption', 'Hasil pemeriksaan')
        ->set('serviceJobId', (string) $job->id)->call('savePhoto')
        ->assertHasNoErrors()->assertSet('photo', null)
        ->assertSee('Foto berhasil disimpan.')->assertSee('Hasil pemeriksaan')->assertSee('Periksa rem');
    $photo = ServiceDocumentation::firstOrFail();
    expect($photo->service_job_id)->toBe($job->id)->and($photo->uploaded_by)->toBe($this->actor->id);
    Storage::disk('local')->assertExists($photo->path);
});

it('denies customer and unassigned mechanic gallery mount', function (string $role) {
    Livewire::actingAs(User::factory()->create(['role' => $role]))->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id])->assertForbidden();
})->with(['customer', 'mechanic']);

it('rechecks mechanic assignment on subsequent component requests', function () {
    $mechanic = User::factory()->create(['role' => 'mechanic']);
    $this->order->update(['mechanic_id' => $mechanic->id]);
    $component = Livewire::actingAs($mechanic)->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id]);
    $this->order->update(['mechanic_id' => null]);
    $component->set('caption', 'stale')->assertForbidden();
});

it('requires a confirmation before soft removing evidence from gallery', function () {
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $this->order, UploadedFile::fake()->image('ok.png'), ['category' => 'before', 'caption' => 'Kondisi awal']);
    $component = Livewire::actingAs($this->actor)->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id]);
    $component->call('remove')->assertHasErrors('photo');
    expect($photo->fresh()->trashed())->toBeFalse();
    $component->call('requestRemoval', $photo->id)->assertSee('Hapus foto dari galeri?')->call('cancelRemoval')->assertSet('pendingRemovalId', null);
    $component->call('requestRemoval', $photo->id)->call('remove')->assertHasNoErrors()->assertSet('pendingRemovalId', null)->assertSee('Belum ada foto dokumentasi.');
    expect($photo->fresh()->trashed())->toBeTrue();
    Storage::disk('local')->assertExists($photo->path);
});

it('does not allow another order photo removal via forged id', function () {
    $other = ServiceOrder::factory()->create();
    $photo = app(ManageServicePhoto::class)->upload($this->actor, $other, UploadedFile::fake()->image('ok.png'), ['category' => 'before']);
    Livewire::actingAs($this->actor)->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id])->call('requestRemoval', $photo->id)->assertNotFound();
    expect($photo->fresh()->trashed())->toBeFalse();
});

it('rejects invalid upload and cross-order job in UI', function (string $invalid) {
    $component = Livewire::actingAs($this->actor)->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id]);
    if ($invalid === 'image') {
        $component->set('photo', UploadedFile::fake()->createWithContent('bad.png', '<svg>evil</svg>'))->call('savePhoto')->assertHasErrors('photo');
    } else {
        $component->set('photo', UploadedFile::fake()->image('ok.jpg'))->set('serviceJobId', (string) ServiceJob::factory()->create()->id)->call('savePhoto')->assertHasErrors('service_job_id');
    }
    expect(ServiceDocumentation::count())->toBe(0);
})->with(['image', 'job']);

it('shows readonly closed galleries and rejects stale upload submission', function (string $status) {
    $component = Livewire::actingAs($this->actor)->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id])->set('photo', UploadedFile::fake()->image('ok.jpg'));
    $this->order->update(['status' => $status]);
    $component->call('savePhoto')->assertHasErrors('photo')->assertSee('Dokumentasi hanya dapat dilihat.')->assertDontSee('Simpan foto');
    expect(ServiceDocumentation::count())->toBe(0);
})->with(['delivered', 'cancelled']);

it('only previews validated temporary raster uploads', function () {
    $component = Livewire::actingAs($this->actor)->test(ServicePhotos::class, ['serviceOrderId' => $this->order->id]);
    $component->set('photo', UploadedFile::fake()->image('ok.png'))->assertSee('Pratinjau foto');
    $component->set('photo', UploadedFile::fake()->createWithContent('bad.png', '<svg>evil</svg>'))->assertHasErrors('photo')->assertDontSee('Pratinjau foto');
});

it('factory creates documentation with supported category and private path', function () {
    $photo = ServiceDocumentation::factory()->create();
    expect($photo->serviceOrder)->toBeInstanceOf(ServiceOrder::class)
        ->and($photo->disk)->toBe('local')->and($photo->category)->toBeInstanceOf(DocumentationCategory::class);
});
