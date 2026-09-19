<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\Services\GuestBookingFinder;
use App\Domains\Appointments\Application\UseCases\ShowGuestBooking;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\Appointments\ValueObjects\Canceller;
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

    $this->policies = Mockery::mock(CancellationPolicy::class);
    $this->policies->shouldReceive('forBusiness')->andReturn(CancellationRule::ofMinutes(120));

    $this->build = fn (string $now = AppointmentFixtures::NOW): ShowGuestBooking => new ShowGuestBooking(
        new GuestBookingFinder($this->appointments),
        $this->policies,
        new GuestBookingPresenter($this->services, $this->customers, $this->staff),
        new FakeClock(AppointmentFixtures::instant($now)),
    );

    $this->show = fn (string $now = AppointmentFixtures::NOW, ...$overrides): UseCaseResponse => ($this->build)($now)
        ->handle(AppointmentFixtures::showGuestInput(...$overrides));
});

describe('a guest opening their booking link', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());
    });

    it('answers with the booking, field by field', function () {
        $booking = ($this->show)()->value();

        expect($booking)->toBeInstanceOf(GuestBookingData::class)
            ->and($booking->referenceCode)->toBe(AppointmentFixtures::REFERENCE_CODE)
            ->and($booking->customerName)->toBe(AppointmentFixtures::CUSTOMER_NAME)
            ->and($booking->serviceName)->toBe(AppointmentFixtures::SERVICE_NAME)
            ->and($booking->staffMemberName)->toBe(AppointmentFixtures::STAFF_NAME)
            ->and($booking->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($booking->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT))
            ->and($booking->status)->toBe(AppointmentStatus::Booked)
            ->and($booking->cancelledAt)->toBeNull()
            ->and($booking->cancellationWindowMinutes)->toBe(120)
            ->and($booking->changeable)->toBeTrue();
    });

    it('marks the booking unchangeable once the cancellation window has closed', function () {
        expect(($this->show)('2026-03-10T08:30:00+00:00')->value()->changeable)->toBeFalse();
    });

    it('writes nothing, because opening a link is a read', function () {
        ($this->show)();

        expect($this->appointments->saved)->toBe([])
            ->and($this->journal->entries)->not->toContain('appointments.save');
    });

    it('looks the booking up in the business whose page the link belongs to', function () {
        ($this->show)();

        expect($this->appointments->referenceCodeLookups)->toHaveCount(1)
            ->and($this->appointments->referenceCodeLookups[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->appointments->referenceCodeLookups[0]['referenceCode'])
            ->toBe(AppointmentFixtures::REFERENCE_CODE);
    });

    it('carries no appointment uuid, no customer email and no notes', function () {
        $booking = ($this->show)()->value();

        expect(json_encode($booking, JSON_THROW_ON_ERROR))
            ->not->toContain(AppointmentFixtures::APPOINTMENT_ID)
            ->and(json_encode($booking, JSON_THROW_ON_ERROR))->not->toContain(AppointmentFixtures::CUSTOMER_EMAIL)
            ->and(json_encode($booking, JSON_THROW_ON_ERROR))->not->toContain(AppointmentFixtures::MANAGE_TOKEN);
    });

    it('shows a cancelled booking as cancelled rather than hiding it', function () {
        $cancelled = AppointmentFixtures::guestAppointment();
        $cancelled->cancel(Canceller::Customer, AppointmentFixtures::now());

        $this->appointments->store($cancelled);

        $booking = ($this->show)()->value();

        expect($booking->status)->toBe(AppointmentStatus::Cancelled)
            ->and($booking->cancelledAt)->toEqual(AppointmentFixtures::now())
            ->and($booking->changeable)->toBeFalse();
    });
});

describe('credentials that do not open a booking', function () {
    beforeEach(function () {
        $this->appointments->store(AppointmentFixtures::guestAppointment());

        $this->refusalFor = fn (string $now, ...$overrides): array => [
            'code' => ($this->show)($now, ...$overrides)->error()->code,
            'kind' => ($this->show)($now, ...$overrides)->error()->kind,
            'message' => ($this->show)($now, ...$overrides)->error()->cause()?->getMessage(),
        ];
    });

    it('refuses a reservation code no booking answers to', function () {
        $response = ($this->show)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            referenceCode: AppointmentFixtures::UNKNOWN_REFERENCE_CODE,
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('guest_booking_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('refuses the right reservation code with the wrong manage token', function () {
        $response = ($this->show)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('guest_booking_not_found');
    });

    it('refuses the right credentials once the manage token has expired', function () {
        $response = ($this->show)('2026-03-18T09:00:00+00:00');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('guest_booking_not_found');
    });

    it('refuses at the exact instant the manage token expires', function () {
        expect(($this->show)(AppointmentFixtures::MANAGE_TOKEN_EXPIRES_AT)->error()->code)
            ->toBe('guest_booking_not_found');
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
            ->and($unknownCode['code'])->toBe('guest_booking_not_found');
    });

    it('refuses a malformed reservation code as not found, never as malformed', function (string $referenceCode) {
        $response = ($this->show)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            referenceCode: $referenceCode,
        ));

        expect($response->error()->code)->toBe('guest_booking_not_found')
            ->and($response->error()->code)->not->toBe('invalid_reference_code');
    })->with([
        'empty' => '',
        'too short' => 'A2B3',
        'too long' => 'A2B3C4D5E6',
        'outside the alphabet' => 'A2B3C4D0',
        'spaces' => '        ',
    ]);

    it('refuses a malformed manage token as not found, never as malformed', function (string $manageToken) {
        $response = ($this->show)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: $manageToken,
        ));

        expect($response->error()->code)->toBe('guest_booking_not_found')
            ->and($response->error()->code)->not->toBe('invalid_manage_token');
    })->with([
        'empty' => '',
        'too short' => 'a1b2c3',
        'not hexadecimal' => 'z1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9f',
    ]);

    it('refuses a booking of a neighbouring business holding the very same code', function () {
        $response = ($this->show)(AppointmentFixtures::NOW, businessId: AppointmentFixtures::OTHER_BUSINESS_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('guest_booking_not_found');
    });

    it('never reveals which of the two credentials was wrong in the message', function () {
        $message = (string) ($this->show)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            manageToken: AppointmentFixtures::OTHER_MANAGE_TOKEN,
        ))->error()->cause()?->getMessage();

        expect($message)->not->toContain(AppointmentFixtures::REFERENCE_CODE)
            ->and($message)->not->toContain(AppointmentFixtures::OTHER_MANAGE_TOKEN)
            ->and($message)->not->toContain(AppointmentFixtures::MANAGE_TOKEN);
    });

    it('writes nothing when it refuses', function () {
        ($this->show)(AppointmentFixtures::NOW, credentials: AppointmentFixtures::credentials(
            referenceCode: AppointmentFixtures::UNKNOWN_REFERENCE_CODE,
        ));

        expect($this->appointments->saved)->toBe([]);
    });
});

describe('a booking that was never made through the public page', function () {
    it('refuses an appointment the business entered itself, which carries no reservation code', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        expect(($this->show)()->error()->code)->toBe('guest_booking_not_found');
    });
});
