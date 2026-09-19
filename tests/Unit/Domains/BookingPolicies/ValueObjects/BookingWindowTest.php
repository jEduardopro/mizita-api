<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Exceptions\InvalidBookingWindow;
use App\Domains\BookingPolicies\ValueObjects\BookingWindow;
use App\Shared\ValueObjects\DomainFailureKind;

it('accepts a horizon inside the bounds the domain declares', function (int $minutes) {
    $window = BookingWindow::ofMinutes($minutes);

    expect($window->minutes())->toBe($minutes)
        ->and($window->isUnlimited())->toBeFalse();
})->with([
    'one minute' => BookingWindow::MINIMUM_MINUTES,
    'a day' => 1440,
    'thirty days' => 43200,
    'a year' => BookingWindow::HARD_CAP_MINUTES,
]);

it('refuses a horizon outside the bounds the domain declares', function (int $minutes) {
    expect(fn () => BookingWindow::ofMinutes($minutes))->toThrow(InvalidBookingWindow::class);
})->with([
    'no horizon at all' => 0,
    'a negative horizon' => -1,
    'one minute past the cap' => BookingWindow::HARD_CAP_MINUTES + 1,
]);

it('refuses zero minutes, because a horizon nobody can book inside is a mistake, not unlimited', function () {
    expect(fn () => BookingWindow::ofMinutes(0))->toThrow(InvalidBookingWindow::class)
        ->and(BookingWindow::unlimited()->minutes())->toBeNull();
});

it('holds nothing at all for a business that books as far ahead as it likes', function () {
    $window = BookingWindow::unlimited();

    expect($window->minutes())->toBeNull()
        ->and($window->isUnlimited())->toBeTrue();
});

it('knows a bounded horizon is not an unlimited one', function () {
    expect(BookingWindow::ofMinutes(1)->isUnlimited())->toBeFalse()
        ->and(BookingWindow::restore(null)->isUnlimited())->toBeTrue()
        ->and(BookingWindow::restore(0)->isUnlimited())->toBeFalse();
});

it('refuses as a domain failure the responder can classify', function () {
    try {
        BookingWindow::ofMinutes(0);
        $thrown = null;
    } catch (InvalidBookingWindow $refusal) {
        $thrown = $refusal;
    }

    expect($thrown?->errorCode())->toBe('invalid_booking_window')
        ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
});

it('restores whatever the column holds, null included', function () {
    expect(BookingWindow::restore(null)->minutes())->toBeNull()
        ->and(BookingWindow::restore(0)->minutes())->toBe(0);
});

it('holds an unlimited horizon equal to another unlimited one, and unequal to a bounded one', function () {
    expect(BookingWindow::unlimited()->equals(BookingWindow::unlimited()))->toBeTrue()
        ->and(BookingWindow::unlimited()->equals(BookingWindow::ofMinutes(1440)))->toBeFalse()
        ->and(BookingWindow::ofMinutes(1440)->equals(BookingWindow::ofMinutes(1440)))->toBeTrue();
});
