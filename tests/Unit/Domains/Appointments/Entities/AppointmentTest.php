<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentAlreadyCancelled;
use App\Domains\Appointments\Exceptions\AppointmentAlreadyStarted;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\BookingSource;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\FakeBusinessContext;

function appointmentInstant(string $value): DateTimeImmutable
{
    return AppointmentFixtures::instant($value);
}

function laterSlot(): AppointmentSlot
{
    return AppointmentSlot::restore(
        appointmentInstant('2026-03-11T09:00:00+00:00'),
        appointmentInstant('2026-03-11T10:00:00+00:00'),
    );
}

describe('booking as a guest', function () {
    beforeEach(function () {
        $this->appointment = AppointmentFixtures::guestAppointment();
    });

    it('carries the reservation code the customer quotes', function () {
        expect($this->appointment->referenceCode()?->value)->toBe(AppointmentFixtures::REFERENCE_CODE);
    });

    it('stores the digest of the management credential, never the credential itself', function () {
        expect($this->appointment->manageTokenHash())->not->toBe(AppointmentFixtures::MANAGE_TOKEN)
            ->and($this->appointment->manageTokenHash())->not->toBeNull()
            ->and($this->appointment->manageTokenExpiresAt())
            ->toEqual(appointmentInstant(AppointmentFixtures::MANAGE_TOKEN_EXPIRES_AT));
    });

    it('records that the booking came from the public side', function () {
        expect($this->appointment->source())->toBe(BookingSource::Public)
            ->and(AppointmentFixtures::appointment()->source())->toBe(BookingSource::Admin);
    });

    it('starts open, with nobody having cancelled it', function () {
        expect($this->appointment->isCancelled())->toBeFalse()
            ->and($this->appointment->cancelledAt())->toBeNull()
            ->and($this->appointment->cancelledBy())->toBeNull();
    });

    it('carries every neighbour as the uuid, never a row number', function () {
        expect($this->appointment->id)->toMatch('/^[0-9a-f-]{36}$/i')
            ->and($this->appointment->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->appointment->customerId())->toMatch('/^[0-9a-f-]{36}$/i')
            ->and($this->appointment->serviceId())->toMatch('/^[0-9a-f-]{36}$/i')
            ->and($this->appointment->staffMemberId())->toMatch('/^[0-9a-f-]{36}$/i');
    });
});

describe('cancelling', function () {
    it('records who cancelled it and when', function () {
        $appointment = AppointmentFixtures::appointment();
        $now = appointmentInstant('2026-03-09T09:00:00+00:00');

        $appointment->cancel(Canceller::Customer, $now);

        expect($appointment->isCancelled())->toBeTrue()
            ->and($appointment->cancelledAt())->toEqual($now)
            ->and($appointment->cancelledBy())->toBe(Canceller::Customer);
    });

    it('refuses a second cancellation of an appointment already cancelled', function () {
        $appointment = AppointmentFixtures::appointment();

        $appointment->cancel(Canceller::Customer, appointmentInstant('2026-03-09T09:00:00+00:00'));

        expect(fn () => $appointment->cancel(Canceller::Business, appointmentInstant('2026-03-09T10:00:00+00:00')))
            ->toThrow(AppointmentAlreadyCancelled::class);
    });

    it('keeps the first cancellation when a second one is refused', function () {
        $appointment = AppointmentFixtures::appointment();
        $first = appointmentInstant('2026-03-09T09:00:00+00:00');

        $appointment->cancel(Canceller::Customer, $first);

        try {
            $appointment->cancel(Canceller::Business, appointmentInstant('2026-03-09T10:00:00+00:00'));
        } catch (AppointmentAlreadyCancelled) {
        }

        expect($appointment->cancelledAt())->toEqual($first)
            ->and($appointment->cancelledBy())->toBe(Canceller::Customer);
    });

    it('refuses a cancellation once the appointment has started', function (string $now) {
        $appointment = AppointmentFixtures::appointment();

        expect(fn () => $appointment->cancel(Canceller::Customer, appointmentInstant($now)))
            ->toThrow(AppointmentAlreadyStarted::class);
    })->with([
        'at the exact start' => AppointmentFixtures::STARTS_AT,
        'one second after the start' => '2026-03-10T09:00:01+00:00',
        'after it ended' => '2026-03-11T09:00:00+00:00',
    ]);

    it('allows a cancellation one second before the start', function () {
        $appointment = AppointmentFixtures::appointment();

        $appointment->cancel(Canceller::Business, appointmentInstant('2026-03-10T08:59:59+00:00'));

        expect($appointment->isCancelled())->toBeTrue();
    });

    it('leaves the appointment open when it refused', function () {
        $appointment = AppointmentFixtures::appointment();

        try {
            $appointment->cancel(Canceller::Customer, appointmentInstant(AppointmentFixtures::STARTS_AT));
        } catch (AppointmentAlreadyStarted) {
        }

        expect($appointment->isCancelled())->toBeFalse()
            ->and($appointment->cancelledBy())->toBeNull();
    });

    it('names the cancellation already made before the start that has passed', function () {
        $appointment = AppointmentFixtures::appointment();

        $appointment->cancel(Canceller::Customer, appointmentInstant('2026-03-09T09:00:00+00:00'));

        expect(fn () => $appointment->cancel(Canceller::Business, appointmentInstant('2026-03-11T09:00:00+00:00')))
            ->toThrow(AppointmentAlreadyCancelled::class);
    });

    it('refuses a cancelled appointment as a conflict the responder can classify', function () {
        $appointment = AppointmentFixtures::appointment(cancelledAt: '2026-03-09T09:00:00+00:00');

        try {
            $appointment->cancel(Canceller::Customer, appointmentInstant('2026-03-09T10:00:00+00:00'));
            $thrown = null;
        } catch (AppointmentAlreadyCancelled $refusal) {
            $thrown = $refusal;
        }

        expect($thrown?->errorCode())->toBe('appointment_already_cancelled')
            ->and($thrown?->kind())->toBe(DomainFailureKind::Conflict);
    });

    it('refuses a started appointment as a conflict the responder can classify', function () {
        try {
            AppointmentFixtures::appointment()
                ->cancel(Canceller::Customer, appointmentInstant(AppointmentFixtures::STARTS_AT));
            $thrown = null;
        } catch (AppointmentAlreadyStarted $refusal) {
            $thrown = $refusal;
        }

        expect($thrown?->errorCode())->toBe('appointment_already_started')
            ->and($thrown?->kind())->toBe(DomainFailureKind::Conflict);
    });
});

describe('rescheduling', function () {
    it('moves the appointment to the slot it was handed', function () {
        $appointment = AppointmentFixtures::appointment();

        $appointment->rescheduleTo(laterSlot(), appointmentInstant('2026-03-09T09:00:00+00:00'));

        expect($appointment->slot()->startsAt)->toEqual(appointmentInstant('2026-03-11T09:00:00+00:00'))
            ->and($appointment->slot()->endsAt)->toEqual(appointmentInstant('2026-03-11T10:00:00+00:00'));
    });

    it('refuses to move an appointment that was already cancelled', function () {
        $appointment = AppointmentFixtures::appointment(cancelledAt: '2026-03-09T09:00:00+00:00');

        expect(fn () => $appointment->rescheduleTo(laterSlot(), appointmentInstant('2026-03-09T10:00:00+00:00')))
            ->toThrow(AppointmentAlreadyCancelled::class);
    });

    it('refuses to move an appointment that has already started', function (string $now) {
        $appointment = AppointmentFixtures::appointment();

        expect(fn () => $appointment->rescheduleTo(laterSlot(), appointmentInstant($now)))
            ->toThrow(AppointmentAlreadyStarted::class);
    })->with([
        'at the exact start' => AppointmentFixtures::STARTS_AT,
        'one second after the start' => '2026-03-10T09:00:01+00:00',
    ]);

    it('leaves the slot exactly where it was when it refused', function () {
        $appointment = AppointmentFixtures::appointment();

        try {
            $appointment->rescheduleTo(laterSlot(), appointmentInstant(AppointmentFixtures::STARTS_AT));
        } catch (AppointmentAlreadyStarted) {
        }

        expect($appointment->slot()->startsAt)->toEqual(appointmentInstant(AppointmentFixtures::STARTS_AT))
            ->and($appointment->slot()->endsAt)->toEqual(appointmentInstant(AppointmentFixtures::ENDS_AT));
    });
});

describe('checking a management credential', function () {
    it('accepts the credential the link was issued with', function () {
        $appointment = AppointmentFixtures::guestAppointment();

        expect($appointment->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant('2026-03-09T09:00:00+00:00'),
        ))->toBeTrue();
    });

    it('rejects a credential the link was not issued with', function (string $candidate) {
        $appointment = AppointmentFixtures::guestAppointment();

        expect($appointment->hasValidManageToken($candidate, appointmentInstant('2026-03-09T09:00:00+00:00')))
            ->toBeFalse();
    })->with([
        'another token' => AppointmentFixtures::OTHER_MANAGE_TOKEN,
        'nothing at all' => '',
        'the token uppercased' => strtoupper(AppointmentFixtures::MANAGE_TOKEN),
    ]);

    it('fails closed when the appointment carries no credential at all', function () {
        $appointment = AppointmentFixtures::appointment(
            manageTokenHash: null,
            manageTokenExpiresAt: AppointmentFixtures::MANAGE_TOKEN_EXPIRES_AT,
        );

        expect($appointment->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant('2026-03-09T09:00:00+00:00'),
        ))->toBeFalse();
    });

    it('fails closed when the credential carries no expiry', function () {
        $appointment = AppointmentFixtures::appointment(
            manageTokenHash: hash('sha256', AppointmentFixtures::MANAGE_TOKEN),
            manageTokenExpiresAt: null,
        );

        expect($appointment->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant('2026-03-09T09:00:00+00:00'),
        ))->toBeFalse();
    });

    it('fails closed for an appointment nobody booked as a guest', function () {
        expect(AppointmentFixtures::appointment()->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant('2026-03-09T09:00:00+00:00'),
        ))->toBeFalse();
    });

    it('fails closed at the exact instant the credential expires', function () {
        $appointment = AppointmentFixtures::guestAppointment();

        expect($appointment->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant(AppointmentFixtures::MANAGE_TOKEN_EXPIRES_AT),
        ))->toBeFalse();
    });

    it('fails closed once the credential has expired', function () {
        $appointment = AppointmentFixtures::guestAppointment();

        expect($appointment->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant('2026-03-17T09:00:01+00:00'),
        ))->toBeFalse();
    });

    it('accepts the credential one second before it expires', function () {
        $appointment = AppointmentFixtures::guestAppointment();

        expect($appointment->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant('2026-03-17T08:59:59+00:00'),
        ))->toBeTrue();
    });

    it('accepts the credential of a cancelled appointment, so the guest can still read it', function () {
        $appointment = AppointmentFixtures::guestAppointment();

        $appointment->cancel(Canceller::Customer, appointmentInstant('2026-03-09T09:00:00+00:00'));

        expect($appointment->hasValidManageToken(
            AppointmentFixtures::MANAGE_TOKEN,
            appointmentInstant('2026-03-09T10:00:00+00:00'),
        ))->toBeTrue();
    });

    it('answers only about the appointment it belongs to', function () {
        $mine = AppointmentFixtures::guestAppointment();
        $theirs = AppointmentFixtures::guestAppointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        );
        $now = appointmentInstant('2026-03-09T09:00:00+00:00');

        expect($mine->hasValidManageToken(AppointmentFixtures::OTHER_MANAGE_TOKEN, $now))->toBeFalse()
            ->and($theirs->hasValidManageToken(AppointmentFixtures::OTHER_MANAGE_TOKEN, $now))->toBeTrue();
    });
});

it('takes a reservation code assigned after the booking was made', function () {
    $appointment = AppointmentFixtures::appointment();

    expect($appointment->referenceCode())->toBeNull();

    $appointment->assignReferenceCode(ReferenceCode::fromString(AppointmentFixtures::REFERENCE_CODE));

    expect($appointment->referenceCode()?->value)->toBe(AppointmentFixtures::REFERENCE_CODE);
});
