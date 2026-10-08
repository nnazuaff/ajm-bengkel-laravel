<?php

use App\Livewire\WorkshopSettings;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkshopSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\ComponentAttributeBag;
use Livewire\Livewire;

it('stores a separate horizontal logo and uses it across navigation', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'owner']);
    Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('horizontalLogo', UploadedFile::fake()->image('horizontal.png', 1600, 560))
        ->call('save')->assertHasNoErrors()->assertSet('horizontalLogo', null);
    expect(AuditLog::where('action', 'workshop.settings_updated')->first()->context['fields'])->toContain('horizontal_logo_path');
    $setting = WorkshopSetting::current();
    $path = $setting->horizontal_logo_path;
    expect($path)->toMatch('/^logos\/[a-zA-Z0-9]+\.png$/')->and($setting->logo_path)->toBeNull();
    Storage::disk('public')->assertExists($path);
    foreach (['/', '/dashboard', '/booking'] as $url) {
        $user = $url === '/booking' ? User::factory()->create() : $owner;
        $html = $this->actingAs($user)->get($url)->assertOk()->getContent();
        $document = new DOMDocument;
        @$document->loadHTML($html);
        $xpath = new DOMXPath($document);
        $brand = $xpath->query('//*[@data-workshop-brand]')->item(0);
        expect($brand)->not->toBeNull();
        $image = $xpath->query('.//img', $brand)->item(0);
        expect($image->getAttribute('src'))->toBe(Storage::disk('public')->url($path))
            ->and($image->getAttribute('alt'))->toBe('AJM Bengkel')
            ->and(trim($brand->textContent))->toBe('');
    }
});

it('rejects invalid horizontal images without writing identity or files', function (string $kind) {
    Storage::fake('public');
    $file = match ($kind) {
        'width' => UploadedFile::fake()->image('logo.png', 1599, 560),
        'height' => UploadedFile::fake()->image('logo.png', 1600, 561),
        'size' => UploadedFile::fake()->image('logo.png', 1600, 560)->size(2049),
        'svg' => UploadedFile::fake()->createWithContent('logo.png', '<svg></svg>'),
    };
    Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(WorkshopSettings::class)
        ->set('horizontalLogo', $file)->call('save')->assertHasErrors(['horizontalLogo']);
    expect(WorkshopSetting::current()->horizontal_logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBe([]);
})->with(['width', 'height', 'size', 'svg']);

it('renders text fallback for missing or unsafe horizontal paths', function (string $path) {
    Storage::fake('public');
    WorkshopSetting::current()->update(['horizontal_logo_path' => $path]);
    expect(WorkshopSetting::current()->horizontalLogoUrl())->toBeNull();
    $html = view('components.app-logo', ['attributes' => new ComponentAttributeBag(['href' => '/'])])->render();
    expect($html)->toContain('AJM Bengkel')->not->toContain('<img');
})->with(['logos/missing.png', '../private.png', 'https://untrusted.test/logo.png']);

it('denies horizontal writes after owner demotion', function () {
    Storage::fake('public');
    $owner = User::factory()->create(['role' => 'owner']);
    $component = Livewire::actingAs($owner)->test(WorkshopSettings::class)
        ->set('horizontalLogo', UploadedFile::fake()->image('logo.png', 1600, 560));
    User::whereKey($owner->id)->update(['role' => 'admin']);
    $component->call('save')->assertForbidden();
    expect(WorkshopSetting::current()->horizontal_logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBe([]);
});

it('rolls back both logo files when audit fails', function () {
    Storage::fake('public');
    $component = Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(WorkshopSettings::class)
        ->set('logo', UploadedFile::fake()->image('square.png'))
        ->set('horizontalLogo', UploadedFile::fake()->image('horizontal.png', 1600, 560));
    AuditLog::creating(fn () => throw new RuntimeException('audit failed'));
    try {
        expect(fn () => $component->call('save'))->toThrow(RuntimeException::class, 'audit failed');
    } finally {
        AuditLog::flushEventListeners();
    }
    expect(WorkshopSetting::current()->horizontal_logo_path)->toBeNull()
        ->and(WorkshopSetting::current()->logo_path)->toBeNull()
        ->and(Storage::disk('public')->allFiles('logos'))->toBe([]);
});
