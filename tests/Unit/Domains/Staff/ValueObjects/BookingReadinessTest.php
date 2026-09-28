<?php

declare(strict_types=1);

use App\Domains\Staff\ValueObjects\BookingLinkBlocker;
use App\Domains\Staff\ValueObjects\BookingReadiness;

it('allows a booking link when nothing blocks it', function () {
    $readiness = BookingReadiness::blockedBy();

    expect($readiness->allowsBookingLink())->toBeTrue()
        ->and($readiness->blockers)->toBe([]);
});

it('withholds a booking link while anything blocks it', function (array $blockers) {
    $readiness = BookingReadiness::blockedBy(...$blockers);

    expect($readiness->allowsBookingLink())->toBeFalse()
        ->and($readiness->blockers)->toBe($blockers);
})->with([
    'no services' => [[BookingLinkBlocker::NoServices]],
    'no working hours' => [[BookingLinkBlocker::NoWorkingHours]],
    'both' => [[BookingLinkBlocker::NoServices, BookingLinkBlocker::NoWorkingHours]],
]);

it('keeps the blockers as a list, whatever keys they arrived under', function () {
    $readiness = BookingReadiness::blockedBy(...['first' => BookingLinkBlocker::NoWorkingHours]);

    expect($readiness->blockers)->toBe([BookingLinkBlocker::NoWorkingHours])
        ->and(array_is_list($readiness->blockers))->toBeTrue();
});

it('names every blocker with the stable code the client switches on', function () {
    expect(array_map(static fn (BookingLinkBlocker $blocker): string => $blocker->value, BookingLinkBlocker::cases()))
        ->toBe(['no_services', 'no_working_hours']);
});
