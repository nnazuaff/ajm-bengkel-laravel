<?php

use App\Actions\ManageCheckInCode;
use App\Livewire\PublicCheckIn;
use App\Models\User;
use Livewire\Livewire;

it('destroys the original guest session before persisting the checkin handoff', function () {
    $code = app(ManageCheckInCode::class)->generate(User::factory()->create(['role' => 'owner']));
    $component = Livewire::test(PublicCheckIn::class);
    $session = session()->driver();
    $previousId = $session->getId();
    $session->save();
    expect($session->getHandler()->read($previousId))->not->toBe('');

    $component->update(calls: [['method' => 'submit', 'params' => [], 'path' => '']], updates: [
        'code' => $code->code, 'name' => 'Guest', 'phone' => '081234567890', 'email' => 'guest@example.test',
        'password' => 'handoff-test-password', 'password_confirmation' => 'handoff-test-password',
    ])->assertHasNoErrors();
    $session->save();

    expect($session->getId())->not->toBe($previousId)
        ->and($session->getHandler()->read($previousId))->toBe('')
        ->and($session->has('check_in.token'))->toBeTrue();
    $this->assertGuest();
});

it('never dehydrates password properties on non submit responses', function () {
    $component = Livewire::test(PublicCheckIn::class)->update(
        calls: [['method' => 'refreshStatus', 'params' => [], 'path' => '']],
        updates: ['password' => 'snapshot-test-password', 'password_confirmation' => 'snapshot-test-password'],
    );
    expect($component->snapshot['data']['password'])->toBe('')
        ->and($component->snapshot['data']['password_confirmation'])->toBe('');
    $component->update(updates: ['password' => 'update-only-test', 'password_confirmation' => 'update-only-test']);
    expect($component->snapshot['data']['password'])->toBe('')
        ->and($component->snapshot['data']['password_confirmation'])->toBe('');
});
