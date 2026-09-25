<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Application\UseCases\UpdateAppointment;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Shared\Contracts\Clock;
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
    $this->journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($this->journal);

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::serviceSnapshot(),
            AppointmentFixtures::serviceSnapshot(
                id: AppointmentFixtures::SECOND_SERVICE_ID,
                name: AppointmentFixtures::SECOND_SERVICE_NAME,
                color: AppointmentFixtures::SECOND_SERVICE_COLOR,
                durationMinutes: AppointmentFixtures::SECOND_SERVICE_DURATION_MINUTES,
            ),
        );

    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::customerSnapshot(),
            AppointmentFixtures::customerSnapshot(
                id: AppointmentFixtures::SECOND_CUSTOMER_ID,
                name: AppointmentFixtures::SECOND_CUSTOMER_NAME,
            ),
        );

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

    $this->build = fn (string $now = AppointmentFixtures::NOW): UpdateAppointment => new UpdateAppointment(
        $this->appointments,
        $this->services,
        $this->customers,
        $this->staff,
        new AppointmentPresenter($this->services, $this->customers, $this->staff, $this->payments),
        new FakeBusinessContext,
        new FakeClock(AppointmentFixtures::instant($now)),
        $this->calendars,
    );

    $this->store = fn (Appointment $appointment): Appointment => tap(
        $appointment,
        fn (Appointment $stored) => $this->appointments->store($stored),
    );

    $this->update = fn (string $now = AppointmentFixtures::NOW, ...$overrides) => ($this->build)($now)
        ->handle(AppointmentFixtures::updateInput(...$overrides));
});

describe('moving an appointment the business still may change', function () {
    beforeEach(function () {
        ($this->store)(AppointmentFixtures::appointment());
    });

    it('answers with every field the client reads back', function () {
        $data = ($this->update)()->value();

        expect($data)->toBeInstanceOf(AppointmentData::class)
            ->and($data->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->and($data->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($data->service->id)->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($data->staffMember->id)->toBe(AppointmentFixtures::STAFF_ID)
            ->and($data->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($data->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT))
            ->and($data->notes)->toBe(AppointmentFixtures::NOTES);
    });

    it('saves the appointment once the guards let the change through', function () {
        ($this->update)();

        expect($this->appointments->saved)->toHaveCount(1)
            ->and($this->appointments->saved[0]->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('moves the slot to the instants the caller asked for', function () {
        ($this->update)(
            AppointmentFixtures::NOW,
            startsAt: '2026-03-11T15:00:00+00:00',
            endsAt: '2026-03-11T16:00:00+00:00',
        );

        $saved = $this->appointments->saved[0];

        expect($saved->slot()->startsAt)->toEqual(new DateTimeImmutable('2026-03-11T15:00:00+00:00'))
            ->and($saved->slot()->endsAt)->toEqual(new DateTimeImmutable('2026-03-11T16:00:00+00:00'));
    });

    it('derives the end from the service duration when the caller sent none', function () {
        ($this->update)(AppointmentFixtures::NOW, endsAt: null);

        expect($this->appointments->saved[0]->slot()->endsAt)
            ->toEqual(AppointmentFixtures::instant(AppointmentFixtures::DERIVED_ENDS_AT));
    });

    it('reassigns the customer, the service and the staff member together', function () {
        $data = ($this->update)(
            AppointmentFixtures::NOW,
            serviceId: AppointmentFixtures::SECOND_SERVICE_ID,
            staffMemberId: AppointmentFixtures::SECOND_STAFF_ID,
        )->value();

        expect($data->service->id)->toBe(AppointmentFixtures::SECOND_SERVICE_ID)
            ->and($data->staffMember->id)->toBe(AppointmentFixtures::SECOND_STAFF_ID);
    });

    it('clears the notes when the caller sent none', function () {
        ($this->update)(AppointmentFixtures::NOW, notes: null);

        expect($this->appointments->saved[0]->notes())->toBeNull();
    });

    it('hands back the appointment uuid, never an internal key', function () {
        $data = ($this->update)()->value();

        expect($data->id)->toBe(AppointmentFixtures::APPOINTMENT_ID)
            ->toBeString()
            ->and(is_numeric($data->id))->toBeFalse();
    });

    it('scopes every read and the save to the business the caller operates in', function () {
        ($this->update)();

        expect(array_unique($this->appointments->businessIdsSeen))->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and($this->appointments->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });
});

describe('an appointment the business already cancelled', function () {
    beforeEach(function () {
        ($this->store)(AppointmentFixtures::appointment(
            cancelledAt: '2026-01-02T10:00:00+00:00',
            cancelledBy: Canceller::Business,
        ));
    });

    it('refuses to move it instead of silently moving it anyway', function () {
        $response = ($this->update)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_cancelled')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('saves nothing at all when it refuses', function () {
        ($this->update)();

        expect($this->appointments->saved)->toBe([])
            ->and($this->journal->entries)->not->toContain('appointments.save');
    });

    it('leaves the stored slot exactly where it was', function () {
        ($this->update)(AppointmentFixtures::NOW, startsAt: '2026-03-11T15:00:00+00:00');

        $stored = $this->appointments->findForBusiness(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
        );

        expect($stored->slot()->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT));
    });
});

describe('an appointment that has already started', function () {
    it('refuses to move it once the start instant has passed', function () {
        ($this->store)(AppointmentFixtures::appointment());

        $response = ($this->update)('2026-03-10T09:30:00+00:00');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_started')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('refuses at the exact instant the appointment starts', function () {
        ($this->store)(AppointmentFixtures::appointment());

        expect(($this->update)(AppointmentFixtures::STARTS_AT)->error()->code)
            ->toBe('appointment_already_started');
    });

    it('still allows the change one second before the appointment starts', function () {
        ($this->store)(AppointmentFixtures::appointment());

        $response = ($this->update)('2026-03-10T08:59:59+00:00');

        expect($response->succeeded())->toBeTrue()
            ->and($this->appointments->saved)->toHaveCount(1);
    });

    it('saves nothing when it refuses a started appointment', function () {
        ($this->store)(AppointmentFixtures::appointment());

        ($this->update)('2026-03-10T09:30:00+00:00');

        expect($this->appointments->saved)->toBe([]);
    });

    it('judges the guard on the clock, never on the new start the caller proposed', function () {
        ($this->store)(AppointmentFixtures::appointment());

        $response = ($this->update)(
            '2026-03-10T09:30:00+00:00',
            startsAt: '2026-04-01T09:00:00+00:00',
            endsAt: '2026-04-01T10:00:00+00:00',
        );

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_already_started');
    });
});

describe('what the use case refuses before it touches the entity', function () {
    it('answers with a refusal when the appointment belongs to nobody it can see', function () {
        $response = ($this->update)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_not_found')
            ->and($this->appointments->saved)->toBe([]);
    });

    it('answers with a refusal when the appointment belongs to another business', function () {
        ($this->store)(AppointmentFixtures::appointment(businessId: AppointmentFixtures::OTHER_BUSINESS_ID));

        expect(($this->update)()->error()->code)->toBe('appointment_not_found');
    });

    it('answers with a refusal for a service the business does not offer', function () {
        ($this->store)(AppointmentFixtures::appointment());

        $response = ($this->update)(AppointmentFixtures::NOW, serviceId: AppointmentFixtures::UNKNOWN_ID);

        expect($response->error()->code)->toBe('appointment_service_not_found')
            ->and($this->appointments->saved)->toBe([]);
    });

    it('answers with a refusal for a customer the business does not know', function () {
        ($this->store)(AppointmentFixtures::appointment());

        $response = ($this->update)(AppointmentFixtures::NOW, customerId: AppointmentFixtures::UNKNOWN_ID);

        expect($response->error()->code)->toBe('appointment_customer_not_found')
            ->and($this->appointments->saved)->toBe([]);
    });

    it('answers with a refusal for a staff member the business does not employ', function () {
        ($this->store)(AppointmentFixtures::appointment());

        $response = ($this->update)(AppointmentFixtures::NOW, staffMemberId: AppointmentFixtures::UNKNOWN_ID);

        expect($response->error()->code)->toBe('appointment_staff_not_found')
            ->and($this->appointments->saved)->toBe([]);
    });
});

describe('the clock it is built with', function () {
    it('takes a clock among its collaborators, so time is never read off the wall', function () {
        $parameters = (new ReflectionMethod(UpdateAppointment::class, '__construct'))->getParameters();
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            $parameters,
        );

        expect($types)->toContain(Clock::class)
            ->and(array_filter($types, static fn (string $type): bool => ! interface_exists($type) && ! class_exists($type)))
            ->toBe([]);
    });

    it('no longer reaches for an unguarded reschedule on the entity', function () {
        expect(method_exists(Appointment::class, 'reschedule'))->toBeFalse()
            ->and(method_exists(Appointment::class, 'rescheduleTo'))->toBeTrue();
    });
});

describe('a caller who keeps only their own calendar', function () {
    beforeEach(function () {
        $this->calendars = FakeCalendarAccess::ownedBy(AppointmentFixtures::STAFF_ID);

        ($this->store)(AppointmentFixtures::appointment());
        ($this->store)(AppointmentFixtures::appointment(
            id: AppointmentFixtures::SECOND_APPOINTMENT_ID,
            staffMemberId: AppointmentFixtures::SECOND_STAFF_ID,
        ));

        $this->updateSecond = fn (...$overrides) => ($this->update)(
            AppointmentFixtures::NOW,
            ...['appointmentId' => AppointmentFixtures::SECOND_APPOINTMENT_ID, ...$overrides],
        );
    });

    it('moves an appointment on their own calendar', function () {
        $response = ($this->update)(
            AppointmentFixtures::NOW,
            startsAt: '2026-03-11T15:00:00+00:00',
            endsAt: '2026-03-11T16:00:00+00:00',
        );

        expect($response->succeeded())->toBeTrue()
            ->and($this->appointments->saved)->toHaveCount(1)
            ->and($this->appointments->saved[0]->id)->toBe(AppointmentFixtures::APPOINTMENT_ID);
    });

    it('answers not found for an appointment on the calendar of another team member', function () {
        $response = ($this->updateSecond)(staffMemberId: AppointmentFixtures::SECOND_STAFF_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->appointments->saved)->toBe([]);
    });

    it('answers not found rather than letting them take over another team member appointment', function () {
        $response = ($this->updateSecond)(staffMemberId: AppointmentFixtures::STAFF_ID);

        $stored = $this->appointments->findForBusiness(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::SECOND_APPOINTMENT_ID,
        );

        expect($response->error()->code)->toBe('appointment_not_found')
            ->and($stored->staffMemberId())->toBe(AppointmentFixtures::SECOND_STAFF_ID)
            ->and($this->appointments->saved)->toBe([]);
    });

    it('refuses to hand their own appointment to another team member', function () {
        $response = ($this->update)(AppointmentFixtures::NOW, staffMemberId: AppointmentFixtures::SECOND_STAFF_ID);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_staff_not_permitted')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->appointments->saved)->toBe([]);
    });

    it('refuses the hand over before it asks any neighbour, and leaves the stored appointment alone', function () {
        ($this->update)(
            AppointmentFixtures::NOW,
            staffMemberId: AppointmentFixtures::SECOND_STAFF_ID,
            startsAt: '2026-03-11T15:00:00+00:00',
            endsAt: '2026-03-11T16:00:00+00:00',
        );

        $entries = $this->journal->entries;
        $stored = $this->appointments->findForBusiness(
            FakeBusinessContext::BUSINESS_ID,
            AppointmentFixtures::APPOINTMENT_ID,
        );

        expect($entries)->toBe(['appointments.findWithinScope'])
            ->and($stored->staffMemberId())->toBe(AppointmentFixtures::STAFF_ID)
            ->and($stored->slot()->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT));
    });

    it('looks the appointment up within the scope the caller was granted', function () {
        ($this->update)();

        expect($this->appointments->scopesSeen)->toHaveCount(1)
            ->and($this->appointments->scopesSeen[0]->restrictedStaffMemberId())->toBe(AppointmentFixtures::STAFF_ID);
    });
});

describe('the calendar the caller is allowed to keep', function () {
    beforeEach(function () {
        ($this->store)(AppointmentFixtures::appointment());
    });

    it('asks for the scope of the account on the input, in the business in context', function () {
        ($this->update)(AppointmentFixtures::NOW, accountId: AppointmentFixtures::SECOND_ACCOUNT_ID);

        expect($this->calendars->lookups)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'accountId' => AppointmentFixtures::SECOND_ACCOUNT_ID,
        ]]);
    });

    it('lets a caller who keeps every calendar hand the appointment to another team member', function () {
        $data = ($this->update)(AppointmentFixtures::NOW, staffMemberId: AppointmentFixtures::SECOND_STAFF_ID)->value();

        expect($data->staffMember->id)->toBe(AppointmentFixtures::SECOND_STAFF_ID);
    });

    it('refuses an account that is no member of the business before it reads the appointment', function () {
        $this->calendars = FakeCalendarAccess::refusing();

        $response = ($this->update)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_accessible')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($this->journal->entries)->toBe([])
            ->and($this->appointments->saved)->toBe([]);
    });
});
