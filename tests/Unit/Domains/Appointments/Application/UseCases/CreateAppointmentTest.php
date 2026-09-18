<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\AppointmentData;
use App\Domains\Appointments\Application\Dtos\CreateAppointmentInput;
use App\Domains\Appointments\Application\Presenters\AppointmentPresenter;
use App\Domains\Appointments\Application\UseCases\CreateAppointment;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\Events\AppointmentCreated;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Appointments\AppointmentFixtures;
use Tests\Support\Appointments\AppointmentJournal;
use Tests\Support\Appointments\FakeAppointmentRepository;
use Tests\Support\Appointments\FakeCustomerDirectory;
use Tests\Support\Appointments\FakeServiceCatalog;
use Tests\Support\Appointments\FakeStaffDirectory;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FixedIdGenerator;

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
        )
        ->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::serviceSnapshot());

    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot())
        ->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::customerSnapshot());

    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot())
        ->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->dispatched = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): array {
        $this->journal->record('events.dispatch');
        $this->dispatched[] = $event;

        return [];
    });

    $this->build = fn (?FakeBusinessContext $business = null): CreateAppointment => new CreateAppointment(
        $this->appointments,
        $this->services,
        $this->customers,
        $this->staff,
        new AppointmentPresenter($this->services, $this->customers, $this->staff),
        new FixedIdGenerator(AppointmentFixtures::GENERATED_APPOINTMENT_ID),
        new FakeClock(AppointmentFixtures::now()),
        $business ?? new FakeBusinessContext,
        $this->events,
    );

    $this->useCase = ($this->build)();

    $this->create = fn (...$overrides) => $this->useCase->handle(AppointmentFixtures::createInput(...$overrides));
});

describe('booking an appointment', function () {
    it('answers with every field the client reads', function () {
        $data = ($this->create)()->value();

        expect($data)->toBeInstanceOf(AppointmentData::class)
            ->and($data->id)->toBe(AppointmentFixtures::GENERATED_APPOINTMENT_ID)
            ->and($data->customer->id)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($data->customer->name)->toBe(AppointmentFixtures::CUSTOMER_NAME)
            ->and($data->customer->email)->toBe(AppointmentFixtures::CUSTOMER_EMAIL)
            ->and($data->service->id)->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($data->service->name)->toBe(AppointmentFixtures::SERVICE_NAME)
            ->and($data->service->color)->toBe(AppointmentFixtures::SERVICE_COLOR)
            ->and($data->service->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($data->staffMember->id)->toBe(AppointmentFixtures::STAFF_ID)
            ->and($data->staffMember->name)->toBe(AppointmentFixtures::STAFF_NAME)
            ->and($data->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($data->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::ENDS_AT))
            ->and($data->durationMinutes)->toBe(90)
            ->and($data->notes)->toBe(AppointmentFixtures::NOTES)
            ->and($data->createdAt)->toEqual(AppointmentFixtures::now());
    });

    it('saves the appointment the identity and the clock handed it', function () {
        ($this->create)();

        expect($this->appointments->saved)->toHaveCount(1);

        $saved = $this->appointments->saved[0];

        expect($saved)->toBeInstanceOf(Appointment::class)
            ->and($saved->id)->toBe(AppointmentFixtures::GENERATED_APPOINTMENT_ID)
            ->and($saved->customerId)->toBe(AppointmentFixtures::CUSTOMER_ID)
            ->and($saved->serviceId())->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($saved->staffMemberId())->toBe(AppointmentFixtures::STAFF_ID)
            ->and($saved->notes()?->value)->toBe(AppointmentFixtures::NOTES)
            ->and($saved->createdAt)->toEqual(AppointmentFixtures::now());
    });

    it('books an appointment that carries no notes at all', function () {
        $data = ($this->create)(notes: null)->value();

        expect($data->notes)->toBeNull()
            ->and($this->appointments->saved[0]->notes())->toBeNull();
    });

    it('files whitespace-only notes as no notes', function () {
        expect(($this->create)(notes: "  \t ")->value()->notes)->toBeNull();
    });

    it('keeps the instant it was handed in UTC, whatever offset the caller wrote it in', function () {
        $data = ($this->create)(
            startsAt: '2026-03-10T10:00:00+01:00',
            endsAt: '2026-03-10T11:00:00+01:00',
        )->value();

        expect($data->startsAt->format(DATE_ATOM))->toBe('2026-03-10T09:00:00+00:00')
            ->and($data->endsAt->format(DATE_ATOM))->toBe('2026-03-10T10:00:00+00:00')
            ->and($data->durationMinutes)->toBe(60);
    });
});

describe('the end of an appointment nobody named', function () {
    it('derives it from the duration of the service booked', function () {
        $data = ($this->create)(endsAt: null)->value();

        expect($data->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::DERIVED_ENDS_AT))
            ->and($data->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($this->appointments->saved[0]->slot()->endsAt)
            ->toEqual(AppointmentFixtures::instant(AppointmentFixtures::DERIVED_ENDS_AT));
    });

    it('derives a different end for a service of a different duration', function () {
        $data = ($this->create)(serviceId: AppointmentFixtures::SECOND_SERVICE_ID, endsAt: null)->value();

        expect($data->durationMinutes)->toBe(AppointmentFixtures::SECOND_SERVICE_DURATION_MINUTES)
            ->and($data->endsAt->format(DATE_ATOM))->toBe('2026-03-10T09:30:00+00:00');
    });

    it('honours the end the caller named instead of the duration of the service', function () {
        $data = ($this->create)(endsAt: '2026-03-10T09:20:00+00:00')->value();

        expect($data->endsAt->format(DATE_ATOM))->toBe('2026-03-10T09:20:00+00:00')
            ->and($data->durationMinutes)->toBe(20)
            ->and($data->service->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES);
    });
});

describe('the business it belongs to', function () {
    it('scopes the appointment to the business in context', function () {
        ($this->create)();

        expect($this->appointments->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('asks every neighbour about the business in context, never about one the payload names', function () {
        $this->useCase->handle(CreateAppointmentInput::fromRequest(AppointmentFixtures::createPayload([
            'business_id' => AppointmentFixtures::OTHER_BUSINESS_ID,
        ])));

        expect($this->appointments->saved[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and(array_unique(array_column($this->services->reads, 'businessId')))
            ->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(array_unique(array_column($this->customers->reads, 'businessId')))
            ->toBe([FakeBusinessContext::BUSINESS_ID])
            ->and(array_unique(array_column($this->staff->reads, 'businessId')))
            ->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('books under whatever business the context names, and asks its neighbours about that one', function () {
        ($this->build)(new FakeBusinessContext(AppointmentFixtures::OTHER_BUSINESS_ID))
            ->handle(AppointmentFixtures::createInput());

        expect($this->appointments->saved[0]->businessId)->toBe(AppointmentFixtures::OTHER_BUSINESS_ID)
            ->and($this->services->reads[0]['businessId'])->toBe(AppointmentFixtures::OTHER_BUSINESS_ID)
            ->and($this->customers->reads[0]['businessId'])->toBe(AppointmentFixtures::OTHER_BUSINESS_ID)
            ->and($this->staff->reads[0]['businessId'])->toBe(AppointmentFixtures::OTHER_BUSINESS_ID);
    });

    it('does not carry the business it belongs to into the data', function () {
        expect(get_object_vars(($this->create)()->value()))->not->toHaveKey('businessId');
    });
});

describe('announcing the booking', function () {
    it('announces the appointment it just created, once, with the identity it handed out', function () {
        ($this->create)();

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(AppointmentCreated::class)
            ->and($this->dispatched[0]->id)->toBe(AppointmentFixtures::GENERATED_APPOINTMENT_ID);
    });

    it('announces only after the appointment is saved and described', function () {
        ($this->create)();

        expect($this->journal->entries)->toBe([
            'services.describe',
            'customers.describe',
            'staff.describe',
            'appointments.save',
            'customers.describe',
            'services.describe',
            'staff.describe',
            'events.dispatch',
        ]);
    });

    it('announces nothing when the booking is refused', function () {
        $this->appointments->failingOnSave(AppointmentOverlaps::withAnotherBooking());

        ($this->create)();

        expect($this->dispatched)->toBe([]);
    });
});

describe('refusing to book', function () {
    it('answers with a conflict when the slot is already taken', function () {
        $this->appointments->failingOnSave(AppointmentOverlaps::withAnotherBooking());

        $response = ($this->create)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_overlap')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->appointments->saved)->toBe([]);
    });

    it('answers with a not found when a neighbour is not one of this business', function (array $overrides, string $code) {
        $response = ($this->create)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->appointments->saved)->toBe([])
            ->and($this->dispatched)->toBe([]);
    })->with([
        'a service nobody offers' => [['serviceId' => AppointmentFixtures::UNKNOWN_ID], 'appointment_service_not_found'],
        'a customer of nobody' => [['customerId' => AppointmentFixtures::UNKNOWN_ID], 'appointment_customer_not_found'],
        'a team member of nobody' => [['staffMemberId' => AppointmentFixtures::UNKNOWN_ID], 'appointment_staff_not_found'],
    ]);

    it('refuses a service that belongs to another business only', function () {
        $this->services->add(AppointmentFixtures::OTHER_BUSINESS_ID, AppointmentFixtures::serviceSnapshot(
            id: AppointmentFixtures::UNKNOWN_ID,
        ));

        expect(($this->create)(serviceId: AppointmentFixtures::UNKNOWN_ID)->error()->code)
            ->toBe('appointment_service_not_found');
    });

    it('refuses what the input itself refuses, before it asks any neighbour', function (array $overrides, string $code) {
        $response = ($this->create)(...$overrides);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($this->journal->entries)->toBe([])
            ->and($this->appointments->saved)->toBe([]);
    })->with([
        'a customer id that is no uuid' => [['customerId' => 'not-a-uuid'], 'appointment_customer_not_found'],
        'a service id that is no uuid' => [['serviceId' => ''], 'appointment_service_not_found'],
        'a team member id that is no uuid' => [['staffMemberId' => '   '], 'appointment_staff_not_found'],
        'a start nobody can read' => [['startsAt' => '10/03/2026 09:00'], 'invalid_appointment_schedule'],
        'an end nobody can read' => [['endsAt' => 'tomorrow'], 'invalid_appointment_schedule'],
        'notes past the maximum' => [['notes' => str_repeat('a', 2001)], 'invalid_appointment_notes'],
    ]);

    it('refuses an appointment that ends before it starts', function () {
        $response = ($this->create)(endsAt: '2026-03-10T08:00:00+00:00');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_appointment_schedule')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->appointments->saved)->toBe([]);
    });

    it('refuses an appointment that runs longer than a day', function () {
        expect(($this->create)(endsAt: '2026-03-11T09:01:00+00:00')->error()->code)
            ->toBe('invalid_appointment_schedule');
    });
});
