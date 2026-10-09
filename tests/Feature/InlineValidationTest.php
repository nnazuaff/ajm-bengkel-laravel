<?php

use App\Actions\ManageCheckInCode;
use App\Actions\SubmitCheckIn;
use App\Livewire\BookingReview;
use App\Livewire\CheckInIntake;
use App\Livewire\ReceiptEditor;
use App\Livewire\StaffEditor;
use App\Models\Booking;
use App\Models\Receipt;
use App\Models\User;
use Livewire\Livewire;

it('shows receipt correction validation only beside the reason field', function () {
    $receipt = Receipt::factory()->create(['status' => 'paid', 'payment_status' => 'paid']);
    $component = Livewire::actingAs(User::factory()->create(['role' => 'owner']))
        ->test(ReceiptEditor::class, ['receipt' => $receipt->id])->call('void')->assertHasErrors('reason');
    $message = $component->instance()->getErrorBag()->first('reason');
    expect(substr_count($component->html(), e($message)))->toBe(1);
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//ui-field[.//textarea]//*[@data-flux-error]')->item(0)->textContent)->toContain($message);
});

it('shows checkin identity validation exactly once beside its checkbox', function () {
    $actor = User::factory()->create(['role' => 'admin']);
    $code = app(ManageCheckInCode::class)->generate($actor);
    $entry = app(SubmitCheckIn::class)->submit(['code' => $code->code, 'name' => 'Guest', 'phone' => '081234567890']);
    $component = Livewire::actingAs($actor)->test(CheckInIntake::class)
        ->call('openForm', $entry->id)->set('form.vehicle.license_plate', 'D1234AA')
        ->set('form.vehicle.brand', 'Honda')->set('form.vehicle.model', 'Vario')
        ->set('form.current_mileage', '10')->set('form.complaint', 'Check')->call('submit')
        ->assertHasErrors('form.identity_verified');
    $message = $component->instance()->getErrorBag()->first('form.identity_verified');
    expect(substr_count($component->html(), e($message)))->toBe(1);
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//ui-field[.//ui-checkbox]//*[@data-flux-error]')->item(0)->textContent)->toContain($message);
});

it('shows staff field validation once', function () {
    $component = Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(StaffEditor::class)
        ->call('create')->call('save')->assertHasErrors('name');
    $message = $component->instance()->getErrorBag()->first('name');
    expect(substr_count($component->html(), e($message)))->toBe(1);
});

it('shows booking ownership validation once at its checkbox', function () {
    $booking = Booking::factory()->create(['status' => 'arrived']);
    $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(BookingReview::class)
        ->call('openBooking', $booking->id)->call('convert')->assertHasErrors('detail.ownership_verified');
    $message = $component->instance()->getErrorBag()->first('detail.ownership_verified');
    expect(substr_count($component->html(), e($message)))->toBe(1);
});

it('shows receipt date errors next to the payment date rather than a banner', function () {
    $receipt = Receipt::factory()->create(['status' => 'final', 'grand_total' => '100.00']);
    $component = Livewire::actingAs(User::factory()->create(['role' => 'owner']))->test(ReceiptEditor::class, ['receipt' => $receipt->id])
        ->set('amount', '10.00')->set('paidAt', 'invalid')->call('pay')->assertHasErrors('paid_at');
    $message = $component->instance()->getErrorBag()->first('paid_at');
    expect(substr_count($component->html(), e($message)))->toBe(1);
    $document = new DOMDocument;
    @$document->loadHTML($component->html());
    $xpath = new DOMXPath($document);
    expect($xpath->query('//ui-field[.//input[@type="datetime-local"]]//*[@data-flux-error]')->item(0)->textContent)->toContain($message);
});

it('keeps non field receipt errors visible', function () {
    $receipt = Receipt::factory()->create(['status' => 'final']);
    Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ReceiptEditor::class, ['receipt' => $receipt->id])
        ->call('save')->assertHasErrors('receipt')->assertSee('Bon final terkunci. Gunakan pembatalan untuk koreksi.');
});

it('keeps malformed hidden row errors visible in the fallback summary', function () {
    $component = Livewire::actingAs(User::factory()->create(['role' => 'admin']))->test(ReceiptEditor::class)
        ->set('items', [['type' => 'forged']])->call('save')->assertHasErrors('items.0.type');
    expect($component->html())->toContain(e($component->instance()->getErrorBag()->first('items.0.type')));
});
