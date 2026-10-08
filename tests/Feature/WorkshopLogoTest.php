<?php

use App\Actions\ManageReceipt;
use App\Enums\Role;
use App\Livewire\WorkshopSettings;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkshopSetting;
use App\Support\ReceiptImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores an owner raster logo on the public disk with a generated name', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->image('workshop.png', 80, 40))
        ->call('save')->assertHasNoErrors();
    $path = WorkshopSetting::current()->logo_path;
    expect($path)->toMatch('/^logos\/[a-zA-Z0-9]+\.png$/');
    Storage::disk('public')->assertExists($path);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->assertSee('Logo bengkel')->assertSee(Storage::disk('public')->url($path), false);
    expect(AuditLog::where('action', 'workshop.settings_updated')->first()->context['fields'])
        ->toContain('logo_path')->not->toContain('logo');
});

it('accepts supported raster formats at the maximum width', function (string $format) {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    $image = imagecreatetruecolor(1024, 64);
    ob_start();
    match ($format) {
        'jpeg' => imagejpeg($image),
        'webp' => imagewebp($image),
        'png' => imagepng($image),
    };
    $bytes = ob_get_clean();
    imagedestroy($image);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->createWithContent('logo.'.$format, $bytes))
        ->call('save')->assertHasNoErrors();
    $path = WorkshopSetting::current()->logo_path;
    expect(ReceiptImage::safeLogoPath($path))->toBe($path)
        ->and(Storage::disk('public')->mimeType($path))->toBe('image/'.$format);
})->with(['jpeg', 'webp', 'png']);

it('previews only validated temporary raster logos', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->image('logo.png'))
        ->assertSee('alt="Pratinjau logo baru"', false);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->createWithContent('logo.png', '<svg></svg>'))
        ->assertDontSee('alt="Pratinjau logo baru"', false);
});

it('renders the historical logo bitmap and preserves it after replacement', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    $bitmap = imagecreatetruecolor(80, 40);
    imagefill($bitmap, 0, 0, imagecolorallocate($bitmap, 220, 20, 40));
    ob_start();
    imagepng($bitmap);
    $bytes = ob_get_clean();
    imagedestroy($bitmap);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->createWithContent('red.png', $bytes))->call('save')->assertHasNoErrors();
    $oldPath = WorkshopSetting::current()->logo_path;
    $action = app(ManageReceipt::class);
    $receipt = $action->finalize($owner, $action->create($owner, ['items' => [
        ['type' => 'custom', 'description' => 'Jasa', 'quantity' => 1, 'unit_price' => '100.00'],
    ]]));
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->image('new.png'))->call('save')->assertHasNoErrors();
    expect(WorkshopSetting::current()->logo_path)->not->toBe($oldPath)
        ->and($receipt->fresh()->workshop_snapshot['logo_path'])->toBe($oldPath);
    Storage::disk('public')->assertExists($oldPath);
    $png = imagecreatefromstring(app(ReceiptImage::class)->render($receipt));
    expect(imagecolorsforindex($png, imagecolorat($png, 108, 108)))
        ->toMatchArray(['red' => 220, 'green' => 20, 'blue' => 40]);
    expect(imagecolorsforindex($png, imagecolorat($png, 108, 50)))
        ->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255]);
    imagedestroy($png);
});

it('shows the snapshotted logo on the web receipt preserving customer navigation', function () {
    Storage::fake('public');
    $logo = UploadedFile::fake()->image('logo.png');
    Storage::disk('public')->put('logos/historical.png', file_get_contents($logo->getPathname()));
    $owner = User::factory()->create(['role' => Role::Owner]);
    WorkshopSetting::current()->update(['logo_path' => 'logos/historical.png']);
    $action = app(ManageReceipt::class);
    $receipt = $action->finalize($owner, $action->create($owner, ['items' => [
        ['type' => 'custom', 'description' => 'Jasa', 'quantity' => 1, 'unit_price' => '100.00'],
    ]]));
    $this->actingAs($owner)->get(route('receipts.show', $receipt))->assertOk()
        ->assertSee('alt="Logo bengkel"', false)
        ->assertSee(Storage::disk('public')->url('logos/historical.png'), false);
    $html = view('receipts.show', ['receipt' => $receipt, 'paid' => '0.00', 'customerReceipt' => true])->render();
    expect($html)->toContain(route('customer.receipts.image', $receipt))->toContain(route('portal'));
});

it('does not persist a logo when the public disk returns a false write', function () {
    $disk = Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    $component = Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->image('logo.png'));
    $failedDisk = Mockery::mock($disk);
    $failedDisk->shouldReceive('put')->andReturnFalse();
    Storage::set('public', $failedDisk);
    $component->call('save')->assertHasErrors(['logo']);
    expect(WorkshopSetting::current()->logo_path)->toBeNull()
        ->and(AuditLog::where('action', 'workshop.settings_updated')->count())->toBe(0);
});

it('cleans only the new logo when settings audit fails', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    $logo = UploadedFile::fake()->image('old.png');
    Storage::disk('public')->put('logos/old.png', file_get_contents($logo->getPathname()));
    WorkshopSetting::current()->update(['logo_path' => 'logos/old.png']);
    $component = Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->image('new.png'))->set('name', 'Changed');
    AuditLog::creating(fn () => throw new RuntimeException('audit failed'));
    try {
        expect(fn () => $component->call('save'))->toThrow(RuntimeException::class, 'audit failed');
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(WorkshopSetting::current()->logo_path)->toBe('logos/old.png')
        ->and(WorkshopSetting::current()->name)->toBe('AJM Bengkel')
        ->and(Storage::disk('public')->allFiles('logos'))->toBe(['logos/old.png']);
});

it('rejects unsafe logo uploads without changing identity', function (string $kind) {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    $file = match ($kind) {
        'svg' => UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><rect width="1" height="1"/></svg>'),
        'text' => UploadedFile::fake()->createWithContent('logo.png', 'not an image'),
        'width' => UploadedFile::fake()->image('logo.png', 1025, 100),
        'height' => UploadedFile::fake()->image('logo.png', 100, 1025),
        'size' => UploadedFile::fake()->image('logo.png')->size(2049),
    };
    Livewire::actingAs($owner)->test(WorkshopSettings::class)->set('logo', $file)
        ->call('save')->assertHasErrors(['logo']);
    expect(WorkshopSetting::current()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBe([]);
})->with(['svg', 'text', 'width', 'height', 'size']);

it('rechecks the upload owner before storage writes', function (Role $role) {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => Role::Owner]);
    $component = Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->image('logo.png'));
    User::whereKey($owner->id)->update(['role' => $role]);
    $component->call('save')->assertForbidden();
    expect(WorkshopSetting::current()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBe([]);
})->with([Role::Admin, Role::Mechanic, Role::Customer]);

it('omits unsafe missing nonraster and oversized stored logo paths', function (string $path, string $kind) {
    Storage::fake('public');
    $logo = UploadedFile::fake()->image('logo.png', $kind === 'dimensions' ? 1025 : 80, 40);
    if ($kind !== 'missing') {
        $contents = match ($kind) {
            'svg' => '<svg xmlns="http://www.w3.org/2000/svg"></svg>',
            'size' => file_get_contents($logo->getPathname()).str_repeat('x', 2097152),
            default => file_get_contents($logo->getPathname()),
        };
        Storage::disk('public')->put($path, $contents);
    }
    expect(ReceiptImage::safeLogoPath($path))->toBeNull();
    WorkshopSetting::current()->update(['logo_path' => $path]);
    $owner = User::factory()->create(['role' => Role::Owner]);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)->assertDontSee('alt="Logo bengkel saat ini"', false);
    $action = app(ManageReceipt::class);
    $receipt = $action->finalize($owner, $action->create($owner, ['items' => [
        ['type' => 'custom', 'description' => 'Jasa', 'quantity' => 1, 'unit_price' => '100.00'],
    ]]));
    $this->actingAs($owner)->get(route('receipts.show', $receipt))->assertOk()->assertDontSee('alt="Logo bengkel"', false);
    $withInvalidLogo = app(ReceiptImage::class)->render($receipt);
    $receipt->workshop_snapshot = array_replace($receipt->workshop_snapshot, ['logo_path' => null]);
    expect($withInvalidLogo)->toBe(app(ReceiptImage::class)->render($receipt));
})->with([
    ['logos/../outside.png', 'missing'],
    ['https://example.com/logo.png', 'missing'],
    ['logos/missing.png', 'missing'],
    ['logos/vector.png', 'svg'],
    ['logos/large.png', 'size'],
    ['logos/wide.png', 'dimensions'],
]);
