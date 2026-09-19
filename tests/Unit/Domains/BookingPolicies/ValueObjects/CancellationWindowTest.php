<?php

declare(strict_types=1);

use App\Domains\BookingPolicies\Exceptions\InvalidCancellationWindow;
use App\Domains\BookingPolicies\ValueObjects\CancellationWindow;
use App\Shared\ValueObjects\DomainFailureKind;

function madridInstant(string $localTime): DateTimeImmutable
{
    return new DateTimeImmutable($localTime, new DateTimeZone('Europe/Madrid'));
}

describe('the window a business allows', function () {
    it('accepts a window inside the bounds the domain declares', function (int $minutes) {
        $window = CancellationWindow::ofMinutes($minutes);

        expect($window->minutes)->toBe($minutes)
            ->and($window->isAllowed())->toBeTrue();
    })->with([
        'until the start' => CancellationWindow::MINIMUM_MINUTES,
        'one minute' => 1,
        'two hours' => 120,
        'thirty days' => CancellationWindow::MAXIMUM_MINUTES,
    ]);

    it('accepts zero minutes as a window that runs until the appointment starts', function () {
        $window = CancellationWindow::ofMinutes(0);

        expect($window->minutes)->toBe(0)
            ->and($window->isAllowed())->toBeTrue();
    });

    it('refuses a window outside the bounds the domain declares', function (int $minutes) {
        expect(fn () => CancellationWindow::ofMinutes($minutes))->toThrow(InvalidCancellationWindow::class);
    })->with([
        'one minute below zero' => -1,
        'one minute past the maximum' => CancellationWindow::MAXIMUM_MINUTES + 1,
    ]);

    it('refuses as a domain failure the responder can classify', function () {
        try {
            CancellationWindow::ofMinutes(-1);
            $thrown = null;
        } catch (InvalidCancellationWindow $refusal) {
            $thrown = $refusal;
        }

        expect($thrown?->errorCode())->toBe('invalid_cancellation_window')
            ->and($thrown?->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('holds nothing at all for a business that allows no cancellation', function () {
        $window = CancellationWindow::notAllowed();

        expect($window->minutes)->toBeNull()
            ->and($window->isAllowed())->toBeFalse();
    });

    it('restores whatever the column holds, null included', function () {
        expect(CancellationWindow::restore(null)->minutes)->toBeNull()
            ->and(CancellationWindow::restore(-5)->minutes)->toBe(-5);
    });

    it('holds two equal windows to be the same', function () {
        expect(CancellationWindow::ofMinutes(120)->equals(CancellationWindow::ofMinutes(120)))->toBeTrue()
            ->and(CancellationWindow::notAllowed()->equals(CancellationWindow::notAllowed()))->toBeTrue()
            ->and(CancellationWindow::notAllowed()->equals(CancellationWindow::ofMinutes(0)))->toBeFalse();
    });
});

describe('deciding whether a customer may still change a booking', function () {
    it('allows a change one minute outside the window', function () {
        $window = CancellationWindow::ofMinutes(120);

        expect($window->allowsChangeAt(
            new DateTimeImmutable('2026-03-10T12:00:00+00:00'),
            new DateTimeImmutable('2026-03-10T09:59:00+00:00'),
        ))->toBeTrue();
    });

    it('refuses a change one minute inside the window', function () {
        $window = CancellationWindow::ofMinutes(120);

        expect($window->allowsChangeAt(
            new DateTimeImmutable('2026-03-10T12:00:00+00:00'),
            new DateTimeImmutable('2026-03-10T10:01:00+00:00'),
        ))->toBeFalse();
    });

    it('allows a change at the exact edge of the window', function () {
        $window = CancellationWindow::ofMinutes(120);

        expect($window->allowsChangeAt(
            new DateTimeImmutable('2026-03-10T12:00:00+00:00'),
            new DateTimeImmutable('2026-03-10T10:00:00+00:00'),
        ))->toBeTrue();
    });

    it('allows a change right up to the start when the window is zero', function () {
        $window = CancellationWindow::ofMinutes(0);
        $startsAt = new DateTimeImmutable('2026-03-10T12:00:00+00:00');

        expect($window->allowsChangeAt($startsAt, $startsAt))->toBeTrue()
            ->and($window->allowsChangeAt($startsAt, new DateTimeImmutable('2026-03-10T12:00:01+00:00')))->toBeFalse();
    });

    it('refuses every change when the business allows no cancellation at all', function () {
        $window = CancellationWindow::notAllowed();

        expect($window->allowsChangeAt(
            new DateTimeImmutable('2026-03-10T12:00:00+00:00'),
            new DateTimeImmutable('2026-01-01T12:00:00+00:00'),
        ))->toBeFalse();
    });

    it('counts elapsed instants, not wall clock hours, on the day the clocks go forward', function () {
        $window = CancellationWindow::ofMinutes(120);

        expect($window->allowsChangeAt(madridInstant('2026-03-29 04:00'), madridInstant('2026-03-29 01:30')))->toBeFalse()
            ->and($window->allowsChangeAt(madridInstant('2026-03-29 05:00'), madridInstant('2026-03-29 01:30')))->toBeTrue();
    });

    it('tells the two passes of 02:30 apart on the day the clocks go back', function () {
        $window = CancellationWindow::ofMinutes(120);
        $startsAt = madridInstant('2026-10-25 04:00');

        expect($window->allowsChangeAt($startsAt, new DateTimeImmutable('2026-10-25T02:30:00+02:00')))->toBeTrue()
            ->and($window->allowsChangeAt($startsAt, new DateTimeImmutable('2026-10-25T02:30:00+01:00')))->toBeFalse();
    });
});
