<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Application\UseCases\ShowCustomerLastAppointment;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakeCalendarAccess;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakePaymentLedger;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;

beforeEach(function () {
    $this->now = '2026-04-01T12:00:00+00:00';
    $this->clock = new FakeClock(AppointmentFixtures::instant($this->now));

    $this->journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($this->journal);

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            phone: AppointmentFixtures::customerPhoneSnapshot(),
        ));
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::staffSnapshot(),
            AppointmentFixtures::staffSnapshot(
                id: AppointmentFixtures::SECOND_STAFF_ID,
                name: AppointmentFixtures::SECOND_STAFF_NAME,
            ),
        );

    $this->payments = new FakePaymentLedger($this->journal);
    $this->calendars = FakeCalendarAccess::everyone();

    $this->build = fn (?FakeBusinessContext $business = null): ShowCustomerLastAppointment => new ShowCustomerLastAppointment(
        $this->appointments,
        $this->customers,
        new AppointmentPresenter($this->services, $this->customers, $this->staff, $this->payments),
        $business ?? new FakeBusinessContext,
        $this->calendars,
        $this->clock,
    );

    $this->show = fn (
        string $customerId = AppointmentFixtures::CUSTOMER_ID,
        string $accountId = AppointmentFixtures::ACCOUNT_ID,
    ) => ($this->build)()->handle(AppointmentFixtures::showCustomerLastInput($customerId, $accountId));
});

describe('the appointment it answers with', function () {
    it('answers with the most recent appointment that already ended', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::APPOINTMENT_ID,
                startsAt: '2026-03-10T09:00:00+00:00',
                endsAt: '2026-03-10T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                startsAt: '2026-03-25T09:00:00+00:00',
                endsAt: '2026-03-25T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::THIRD_APPOINTMENT_ID,
                startsAt: '2026-03-15T09:00:00+00:00',
                endsAt: '2026-03-15T10:00:00+00:00',
            ),
        );

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::SECOND_APPOINTMENT_ID);
    });

    it('describes the appointment field by field', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        $data = ($this->show)()->value();

        expect($data)->toBeInstanceOf(AppointmentData::class)
            ->and($data->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($data->status)->toBe(AppointmentStatus::Booked)
            ->and($data->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($data->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT))
            ->and($data->notes)->toBe(AppointmentFixtures::NOTES)
            ->and($data->createdAt)->toEqual(AppointmentFixtures::now())
            ->and($data->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($data->customer->name)->toBe(AppointmentFixtures::CUSTOMER_NAME)
            ->and($data->customer->phone?->countryCode)->toBe(AppointmentFixtures::CUSTOMER_PHONE_COUNTRY_CODE)
            ->and($data->customer->phone?->nationalNumber)->toBe(AppointmentFixtures::CUSTOMER_PHONE_NATIONAL_NUMBER)
            ->and($data->service->id)->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($data->service->name)->toBe(AppointmentFixtures::SERVICE_NAME)
            ->and($data->service->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($data->service->bufferMinutes)->toBe(AppointmentFixtures::SERVICE_BUFFER_MINUTES)
            ->and($data->service->price)->toBe(AppointmentFixtures::SERVICE_PRICE)
            ->and($data->staffMember->id)->toBe(AppointmentFixtures::STAFF_ID)
            ->and($data->staffMember->name)->toBe(AppointmentFixtures::STAFF_NAME);
    });

    it('hands back uuids, never an internal key', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        $data = ($this->show)()->value();

        expect(is_numeric($data->id))->toBeFalse()
            ->and(is_numeric($data->customer->id))->toBeFalse()
            ->and(is_numeric($data->service->id))->toBeFalse()
            ->and(is_numeric($data->staffMember->id))->toBeFalse();
    });

    it('answers a customer with no appointments with a success carrying nothing', function () {
        $response = ($this->show)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('describes nothing when there is nothing to describe', function () {
        ($this->show)();

        expect($this->services->reads)->toBe([])
            ->and($this->staff->reads)->toBe([]);
    });
});

describe('the moment that splits past from not yet', function () {
    it('counts an appointment that ends exactly now', function () {
        $this->appointments->store(AppointmentFixtures::appointment(
            startsAt: '2026-04-01T11:00:00+00:00',
            endsAt: $this->now,
        ));

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('leaves out an appointment still running now', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-03-20T09:00:00+00:00',
                endsAt: '2026-03-20T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                startsAt: '2026-04-01T11:30:00+00:00',
                endsAt: '2026-04-01T12:00:01+00:00',
            ),
        );

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('leaves out an appointment still to come', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-03-20T09:00:00+00:00',
                endsAt: '2026-03-20T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                startsAt: '2026-04-10T09:00:00+00:00',
                endsAt: '2026-04-10T10:00:00+00:00',
            ),
        );

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('answers nothing when every appointment is still running or to come', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-04-01T11:30:00+00:00',
                endsAt: '2026-04-01T12:30:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                startsAt: '2026-04-10T09:00:00+00:00',
                endsAt: '2026-04-10T10:00:00+00:00',
            ),
        );

        $response = ($this->show)();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBeNull();
    });

    it('takes now from the clock and never from anywhere else', function () {
        ($this->show)();

        expect($this->appointments->lastAttendedLookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'customerId' => AppointmentFixtures::CUSTOMER_ID,
            'now' => $this->now,
        ]]);
    });

    it('answers the appointment that just ended once the clock moves past it', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-03-20T09:00:00+00:00',
                endsAt: '2026-03-20T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                startsAt: '2026-04-01T11:30:00+00:00',
                endsAt: '2026-04-01T12:30:00+00:00',
            ),
        );

        $this->clock->advance('PT30M');

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::SECOND_APPOINTMENT_ID);
    });

    it('compares instants, not the offset an end was written in, across the spring forward in Madrid', function (string $endsAt, ?string $expectedId) {
        $this->clock = new FakeClock(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));
        $this->appointments->store(AppointmentFixtures::appointment(
            startsAt: '2026-03-29T00:30:00+00:00',
            endsAt: $endsAt,
        ));

        expect(($this->show)()->value()?->id)->toBe($expectedId);
    })->with([
        'ends exactly now, written in summer time' => ['2026-03-29T03:30:00+02:00', AppointmentFixtures::APPOINTMENT_ID],
        'ends a minute later, written in summer time' => ['2026-03-29T03:31:00+02:00', null],
    ]);
});

describe('the appointments it leaves out', function () {
    it('leaves out an appointment that was cancelled, however recent', function () {
        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-03-10T09:00:00+00:00',
                endsAt: '2026-03-10T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                startsAt: '2026-03-30T09:00:00+00:00',
                endsAt: '2026-03-30T10:00:00+00:00',
                cancelledAt: '2026-03-01T10:00:00+00:00',
                cancelledBy: Canceller::Customer,
            ),
        );

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('answers nothing when the only past appointment was cancelled', function () {
        $this->appointments->store(AppointmentFixtures::appointment(
            cancelledAt: '2026-03-01T10:00:00+00:00',
            cancelledBy: Canceller::Business,
        ));

        expect(($this->show)()->value())->toBeNull();
    });

    it('leaves out a more recent appointment booked by somebody else', function () {
        $this->customers->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
            name: AppointmentFixtures::SECOND_CUSTOMER_NAME,
        ));
        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-03-10T09:00:00+00:00',
                endsAt: '2026-03-10T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                customerId: AppointmentFixtures::SECOND_CUSTOMER_ID,
                startsAt: '2026-03-30T09:00:00+00:00',
                endsAt: '2026-03-30T10:00:00+00:00',
            ),
        );

        $data = ($this->show)()->value();

        expect($data?->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($data?->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID);
    });

    it('answers nothing for a customer whose only neighbour has a history', function () {
        $this->customers->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
            name: AppointmentFixtures::SECOND_CUSTOMER_NAME,
        ));
        $this->appointments->store(AppointmentFixtures::appointment(
            customerId: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));

        expect(($this->show)()->value())->toBeNull();
    });
});

describe('the business it reads', function () {
    it('shows nothing of the appointments the same customer has at another business', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot());
        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-03-10T09:00:00+00:00',
                endsAt: '2026-03-10T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::FOREIGN_APPOINTMENT_ID,
                businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
                startsAt: '2026-03-30T09:00:00+00:00',
                endsAt: '2026-03-30T10:00:00+00:00',
            ),
        );

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('answers nothing when the only past appointment belongs to another business', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot());
        $this->appointments->store(AppointmentFixtures::appointment(
            id: AppointmentFixtures::FOREIGN_APPOINTMENT_ID,
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
        ));

        expect(($this->show)()->value())->toBeNull();
    });

    it('reads the business in context and never one a caller could name', function () {
        $this->appointments->store(AppointmentFixtures::appointment());

        ($this->show)();

        expect(array_unique($this->appointments->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(array_unique(array_column($this->customers->reads, 'businessId')))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('reads the other business when the context names that other business', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot());

        ($this->build)(new FakeBusinessContext(AppointmentFixtures::OTHER_BUSINESS_ID))
            ->handle(AppointmentFixtures::showCustomerLastInput());

        expect(array_unique($this->appointments->businessIdsSeen))->toBe([AppointmentFixtures::OTHER_BUSINESS_ID]);
    });
});

describe('the customer it may not read', function () {
    it('refuses a customer uuid no business carries and looks nothing up', function () {
        $response = ($this->show)(customerId: AppointmentFixtures::UNKNOWN_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->appointments->lastAttendedLookups)->toBe([])
            ->and($this->calendars->lookups)->toBe([]);
    });

    it('refuses a customer of another business rather than answering nothing', function () {
        $this->customers->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot(
            id: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));
        $this->appointments->store(AppointmentFixtures::appointment(
            id: AppointmentFixtures::FOREIGN_APPOINTMENT_ID,
            businessId: AppointmentFixtures::OTHER_BUSINESS_ID,
            customerId: AppointmentFixtures::SECOND_CUSTOMER_ID,
        ));

        $response = ($this->show)(customerId: AppointmentFixtures::SECOND_CUSTOMER_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->appointments->lastAttendedLookups)->toBe([]);
    });

    it('refuses a malformed customer uuid before it reads anything at all', function (string $customerId) {
        $response = ($this->show)(customerId: $customerId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_customer_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe([])
            ->and($this->calendars->lookups)->toBe([]);
    })->with([
        'a word' => 'not-a-uuid',
        'empty' => '',
        'an integer key' => '7',
    ]);
});

describe('a caller who keeps only their own calendar', function () {
    beforeEach(function () {
        $this->calendars = FakeCalendarAccess::ownedBy(AppointmentFixtures::STAFF_ID);

        $this->appointments->store(
            AppointmentFixtures::appointment(
                startsAt: '2026-03-10T09:00:00+00:00',
                endsAt: '2026-03-10T10:00:00+00:00',
            ),
            AppointmentFixtures::appointment(
                id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
                staffMemberId: AppointmentFixtures::SECOND_STAFF_ID,
                startsAt: '2026-03-30T09:00:00+00:00',
                endsAt: '2026-03-30T10:00:00+00:00',
            ),
        );
    });

    it('answers with the last appointment on their own calendar, passing over a later one of a teammate', function () {
        $data = ($this->show)()->value();

        expect($data?->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($data?->staffMember->id)->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('answers nothing when the customer only ever saw a teammate', function () {
        $this->calendars = FakeCalendarAccess::ownedBy(AppointmentFixtures::UNKNOWN_ID);

        expect(($this->show)()->value())->toBeNull();
    });

    it('hands the repository the scope the caller was granted', function () {
        ($this->show)();

        expect($this->appointments->scopesSeen)->toHaveCount(1)
            ->and($this->appointments->scopesSeen[0]->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });

    it('answers the teammate appointment to a caller who keeps every calendar', function () {
        $this->calendars = FakeCalendarAccess::everyone();

        expect(($this->show)()->value()?->id)->toBe(AppointmentFixtures::SECOND_APPOINTMENT_ID);
    });
});

describe('the calendar the caller is allowed to keep', function () {
    it('asks for the scope of the account on the input, in the business in context', function () {
        ($this->show)(accountId: AppointmentFixtures::SECOND_ACCOUNT_ID);

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => AppointmentFixtures::SECOND_ACCOUNT_ID,
        ]]);
    });

    it('refuses an account that is no member of the business and reads no history', function () {
        $this->calendars = FakeCalendarAccess::refusing();
        $this->appointments->store(AppointmentFixtures::appointment());

        $response = ($this->show)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->appointments->lastAttendedLookups)->toBe([]);
    });

    it('refuses a malformed account before it reads anything at all', function (string $accountId) {
        $response = ($this->show)(accountId: $accountId);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->journal->entries)->toBe([])
            ->and($this->calendars->lookups)->toBe([]);
    })->with([
        'a word' => 'nobody',
        'empty' => '',
        'an integer key' => '7',
    ]);
});
