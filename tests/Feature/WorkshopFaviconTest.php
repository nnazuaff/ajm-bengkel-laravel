<?php

use App\Livewire\WorkshopSettings;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkshopSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores a separate favicon and renders it on public auth and admin pages', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'owner']);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('favicon', UploadedFile::fake()->image('icon.png', 512, 512))
        ->call('save')->assertHasNoErrors()->assertSet('favicon', null);
    $setting = WorkshopSetting::current();
    $path = $setting->favicon_path;
    expect($path)->toMatch('/^logos\/[a-zA-Z0-9]+\.png$/')
        ->and($setting->logo_path)->toBeNull()->and($setting->horizontal_logo_path)->toBeNull();
    Storage::disk('public')->assertExists($path);
    expect(AuditLog::where('action', 'workshop.settings_updated')->first()->context['fields'])->toContain('favicon_path');
    foreach (['/', '/dashboard'] as $url) {
        $this->actingAs($owner)->get($url)->assertOk()
            ->assertSee('rel="icon" href="'.Storage::disk('public')->url($path).'"', false)
            ->assertDontSee('href="/favicon.svg"', false);
    }
    auth()->logout();
    $this->get('/login')->assertOk()->assertSee('rel="icon" href="'.Storage::disk('public')->url($path).'"', false);
});

it('rejects invalid favicon uploads without storing files', function (string $kind) {
    Storage::fake('public');
    $file = match ($kind) {
        'rectangular' => UploadedFile::fake()->image('icon.png', 128, 64),
        'small' => UploadedFile::fake()->image('icon.png', 31, 31),
        'large' => UploadedFile::fake()->image('icon.png', 513, 513),
        'size' => UploadedFile::fake()->image('icon.png', 64, 64)->size(1025),
        'jpeg' => UploadedFile::fake()->image('icon.jpg', 64, 64),
        'svg' => UploadedFile::fake()->createWithContent('icon.png', '<svg></svg>'),
    };
    Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(WorkshopSettings::class)
        ->set('favicon', $file)->call('save')->assertHasErrors(['favicon'])
        ->assertDontSee('alt="Pratinjau favicon baru"', false);
    expect(WorkshopSetting::current()->favicon_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBe([]);
})->with(['rectangular', 'small', 'large', 'size', 'jpeg', 'svg']);

it('uses default favicon for unsafe or missing stored files', function (string $path, string $kind) {
    Storage::fake('public');
    if ($kind !== 'missing') {
        $file = UploadedFile::fake()->image('icon.png', $kind === 'dimensions' ? 513 : 32, $kind === 'dimensions' ? 513 : 32);
        $bytes = $kind === 'svg' ? '<svg></svg>' : file_get_contents($file->getPathname());
        Storage::disk('public')->put($path, $bytes);
    }
    WorkshopSetting::current()->update(['favicon_path' => $path]);
    expect(WorkshopSetting::current()->faviconUrl())->toBeNull();
    $this->get('/')->assertOk()->assertSee('href="/favicon.svg"', false);
})->with([
    ['logos/missing.png', 'missing'], ['../secret.png', 'missing'],
    ['https://untrusted.test/icon.png', 'missing'], ['logos/vector.png', 'svg'], ['logos/oversized.png', 'dimensions'],
]);

it('changes favicon URL on replacement without overwriting previous files', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'owner']);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('favicon', UploadedFile::fake()->image('icon.png', 32, 32))->call('save')->assertHasNoErrors();
    $oldPath = WorkshopSetting::current()->favicon_path;
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('favicon', UploadedFile::fake()->image('replacement.png', 64, 64))->call('save')->assertHasNoErrors();
    expect(WorkshopSetting::current()->favicon_path)->not->toBe($oldPath);
    Storage::disk('public')->assertExists($oldPath);
});

it('denies favicon save after owner role changes', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'owner']);
    $component = Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('favicon', UploadedFile::fake()->image('icon.png', 32, 32));
    User::whereKey($owner->id)->update(['role' => 'admin']);
    $component->call('save')->assertForbidden();
    expect(Storage::disk('public')->allFiles('logos'))->toBe([]);
});

it('rolls back the favicon on audit failure', function () {
    Storage::fake('public');
    $component = Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(WorkshopSettings::class)
        ->set('favicon', UploadedFile::fake()->image('icon.png', 32, 32));
    AuditLog::creating(fn () => throw new RuntimeException('audit failed'));
    try {
        expect(fn () => $component->call('save'))->toThrow(RuntimeException::class, 'audit failed');
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(WorkshopSetting::current()->favicon_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBe([]);
});
