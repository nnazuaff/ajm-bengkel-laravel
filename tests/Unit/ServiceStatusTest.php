<?php

use App\Enums\ServiceStatus;

it('limits service progress to deliberate transitions', function () {
    expect(ServiceStatus::Waiting->canTransitionTo(ServiceStatus::Inspection))->toBeTrue()
        ->and(ServiceStatus::Waiting->canTransitionTo(ServiceStatus::Delivered))->toBeFalse()
        ->and(ServiceStatus::InProgress->canTransitionTo(ServiceStatus::WaitingPart))->toBeTrue()
        ->and(ServiceStatus::Completed->canTransitionTo(ServiceStatus::InProgress))->toBeFalse()
        ->and(ServiceStatus::Delivered->transitions())->toBe([])
        ->and(ServiceStatus::Cancelled->transitions())->toBe([]);
});
