<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\Services\GuestBookingFinder;
use App\Domains\Appointments\Application\UseCases\RescheduleGuestBooking;
use App\Domains\Appointments\Contracts\BookableSlots;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Services\AppointmentChangeWindow;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Domains\Appointments\ValueObjects\ManageTokenExpiry;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

beforeEach(function () {
    $this->journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($this->journal);

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot());
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->slots = Mockery::mock(BookableSlots::class);
    $this->policies = Mockery::mock(CancellationPolicy::class);

    $this->allowChanges = function (?CancellationRule $rule = null, bool $bookable = true): void {
        $this->policies->shouldReceive('forBusiness')->andReturn($rule ?? CancellationRule::ofMinutes(120));
        $this->slots->shouldReceive('isBookable')->andReturnUsing(function (...$arguments) use ($bookable): bool {
            $this->journal->record('slots.isBookable');
            $this->askedAbout = $arguments;

            return $bookable;
        });
    };

    $this->build = fn (string $now = AppointmentFixtures::NOW): RescheduleGuestBooking => new RescheduleGuestBooking(
        $this->appointments,
        new GuestBookingFinder($this->appointments),
        $this->services,
        $this->slots,
        $this->policies,
        new AppointmentChangeWindow,
        new GuestBookingPresenter($this->services, $this->customers, $this->staff),
        new FakeClock(AppointmentFixtures::instant($now)),
    );

    $this->reschedule = fn (string $now = AppointmentFixtures::NOW, ...$overrides): UseCaseResponse => ($this->build)($now)
        ->handle(AppointmentFixtures::rescheduleGuestInput(...$overrides));
});

describe('a guest moving their own booking', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
    });

    it('answers with the moved booking, field by field', function () {
        ($this->allowChanges)();

        $booking = ($this->reschedule)()->value();

        expect($booking)->toBeInstanceOf(GuestBookingData::class)
            ->and($booking->referenceCode)->toBe(AppointmentFixtures::REFERENCE_CODE)
            ->and($booking->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::RESCHEDULED_STARTS_AT))
            ->and($booking->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($booking->cancellationWindowMinutes)->toBe(120);
    });

    it('derives the new end from the duration of the service already booked', function () {
        ($this->allowChanges)();

        ($this->reschedule)();

        expect($this->appointments->saved[0]->slot()->endsAt)
            ->toEqual(new DateTimeImmutable('2026-03-12T11:45:00+00:00'));
    });

    it('pushes the manage token expiry out to match the new slot', function () {
        ($this->allowChanges)();

        ($this->reschedule)();

        $saved = $this->appointments->saved[0];

        expect($saved->manageTokenExpiresAt())->toEqual(ManageTokenExpiry::forSlot($saved->slot()));
    });

    it('excludes the booking being moved from the slots it is checked against', function () {
        ($this->allowChanges)();

        ($this->reschedule)();

        expect($this->askedAbout[4])->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('checks the new start against the service and staff member already booked', function () {
        ($this->allowChanges)();

        ($this->reschedule)();

        expect($this->askedAbout[0])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->askedAbout[1])->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($this->askedAbout[2])->toBe(AppointmentFixtures::STAFF_ID)
            ->and($this->askedAbout[3])
            ->toEqual(AppointmentFixtures::instant(AppointmentFixtures::RESCHEDULED_STARTS_AT));
    });

    it('saves the moved booking exactly once', function () {
        ($this->allowChanges)();

        ($this->reschedule)();

        expect($this->appointments->saved)->toHaveCount(1);
    });

    it('hands back no manage token, because the guest already holds it', function () {
        ($this->allowChanges)();

        $booking = ($this->reschedule)()->value();

        expect($booking)->not->toHaveProperty('manageToken')
            ->and(json_encode($booking, JSON_THROW_ON_ERROR))->not->toContain(AppointmentFixtures::MANAGE_TOKEN);
    });

    it('carries no appointment uuid, customer email or notes back to the guest', function () {
        ($this->allowChanges)();

        $serialized = json_encode(($this->reschedule)()->value(), JSON_THROW_ON_ERROR);

        expect($serialized)->not->toContain(AppointmentFixtures::APPOINTMENT_ID)
            ->and($serialized)->not->toContain(AppointmentFixtures::CUSTOMER_EMAIL);
    });
});

describe('the window consulted before the entity is touched', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
    });

    it('refuses with appointment_changes_not_allowed when the business allows no changes', function () {
        ($this->allowChanges)(CancellationRule::notAllowed());

        $response = ($this->reschedule)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_changes_not_allowed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('refuses with cancellation_window_closed when the window has run out', function () {
        ($this->allowChanges)(CancellationRule::ofMinutes(120));

        $response = ($this->reschedule)('2026-03-10T08:30:00+00:00');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('cancellation_window_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('tells a policy that forbids changes apart from a window that has closed', function () {
        ($this->policies->shouldReceive('forBusiness')->andReturn(CancellationRule::notAllowed()));
        $this->slots->shouldReceive('isBookable')->andReturn(true);

        $forbidden = ($this->reschedule)()->error()->code;

        expect($forbidden)->toBe('appointment_changes_not_allowed')
            ->and($forbidden)->not->toBe('cancellation_window_closed');
    });

    it('allows the change at the exact minute the window still permits', function () {
        ($this->allowChanges)(CancellationRule::ofMinutes(120));

        expect(($this->reschedule)('2026-03-10T07:00:00+00:00')->succeeded())->toBeTrue();
    });

    it('never asks the availability engine once the window has refused', function () {
        $this->policies->shouldReceive('forBusiness')->andReturn(CancellationRule::notAllowed());
        $this->slots->shouldNotReceive('isBookable');

        ($this->reschedule)();

        expect($this->journal->entries)->not->toContain('slots.isBookable');
    });

    it('saves nothing when the window refuses', function (CancellationRule $rule, string $now) {
        ($this->allowChanges)($rule);

        ($this->reschedule)($now);

        expect($this->appointments->saved)->toBe([])
            ->and($this->journal->entries)->not->toContain('appointments.save');
    })->with([
        'changes forbidden' => [fn () => CancellationRule::notAllowed(), AppointmentFixtures::NOW],
        'window closed' => [fn () => CancellationRule::ofMinutes(120), '2026-03-10T08:30:00+00:00'],
    ]);

    it('leaves the stored slot exactly where it was when the window refuses', function () {
        ($this->allowChanges)(CancellationRule::notAllowed());

        ($this->reschedule)();

        expect($this->appointments->findByReferenceCode(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::REFERENCE_CODE,
        )?->slot()->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT));
    });
});

describe('a slot the guest may not move to', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
    });

    it('refuses a start the availability engine will not offer', function () {
        ($this->allowChanges)(bookable: false);

        $response = ($this->reschedule)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_slot_not_bookable')
            ->and($this->appointments->saved)->toBe([]);
    });

    it('refuses a start instant it cannot read', function (string $startsAt) {
        ($this->allowChanges)();

        expect(($this->reschedule)(AppointmentFixtures::NOW, startsAt: $startsAt)->error()->code)
            ->toBe('invalid_appointment_schedule');
    })->with([
        'empty' => '',
        'not a date' => 'next tuesday',
        'a date with no time' => '2026-03-12',
    ]);
});

describe('credentials that do not open a booking', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
        ($this->allowChanges)();

        $this->refusalFor = fn (string $now, ...$overrides): array => [
            'code' => ($this->reschedule)($now, ...$overrides)->error()->code,
            'kind' => ($this->reschedule)($now, ...$overrides)->error()->kind,
            'message' => ($this->reschedule)($now, ...$overrides)->error()->cause()?->getMessage(),
        ];
    });

    it('tells an unknown code, a wrong token and an expired token apart in no way at all', function () {
        $unknownCode = ($this->refusalFor)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            referenceCode: AppointmentFixtures::UNKNOWN_REFERENCE_CODE,
        ));
        $wrongToken = ($this->refusalFor)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        ));
        $expiredToken = ($this->refusalFor)('2026-03-18T09:00:00+00:00');

        expect($unknownCode)->toBe($wrongToken)
            ->and($wrongToken)->toBe($expiredToken)
            ->and($unknownCode['code'])->toBe('guest_booking_not_found')
            ->and($unknownCode['kind'])->toBe(DomainFailureKind::NotFound);
    });

    it('refuses a booking of a neighbouring business holding the very same code', function () {
        expect(($this->reschedule)(AppointmentFixtures::NOW, businessId: AppointmentFixtures::OTHER_BUSINESS_ID)
            ->error()->code)->toBe('guest_booking_not_found');
    });

    it('saves nothing when the credentials do not open a booking', function () {
        ($this->reschedule)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        ));

        expect($this->appointments->saved)->toBe([]);
    });
});

describe('a business whose doors are shut at the moment the guest moves their booking', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
    });

    it('moves the booking to a future slot the availability engine still offers', function () {
        ($this->allowChanges)();

        $response = ($this->reschedule)(AppointmentFixtures::NOW, startsAt: '2026-06-15T11:00:00+00:00');

        expect($response->succeeded())->toBeTrue()
            ->and($this->appointments->saved)->toHaveCount(1)
            ->and($this->appointments->saved[0]->slot()->startsAt)
            ->toEqual(AppointmentFixtures::instant('2026-06-15T11:00:00+00:00'));
    });
});

describe('a booking the entity itself will not move', function () {
    it('refuses one the guest already cancelled', function () {
        $cancelled = AppointmentFixtures::guestAppointment();
        $cancelled->cancel(Canceller::Customer, AppointmentFixtures::now());

        $this->appointments->store($cancelled);
        ($this->allowChanges)();

        $response = ($this->reschedule)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_cancelled')
            ->and($this->appointments->saved)->toBe([]);
    });
});
