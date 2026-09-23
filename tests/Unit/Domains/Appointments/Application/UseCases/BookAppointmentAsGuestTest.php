<?php

declare(strict_types=1);

use App\Domains\Appointments\Application\Dtos\GuestBookingConfirmationData;
use App\Domains\Appointments\Application\Dtos\GuestBookingData;
use App\Domains\Appointments\Application\Presenters\GuestBookingPresenter;
use App\Domains\Appointments\Application\UseCases\BookAppointmentAsGuest;
use App\Domains\Appointments\Application\UseCases\CancelGuestBooking;
use App\Domains\Appointments\Application\UseCases\RescheduleGuestBooking;
use App\Domains\Appointments\Application\UseCases\ShowGuestBooking;
use App\Domains\Appointments\Contracts\BookableSlots;
use App\Domains\Appointments\Contracts\CancellationPolicy;
use App\Domains\Appointments\Contracts\ManageTokenFactory;
use App\Domains\Appointments\Contracts\ReferenceCodeGenerator;
use App\Domains\Appointments\Events\AppointmentBooked;
use App\Domains\Appointments\Exceptions\AppointmentOverlaps;
use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\BookingSource;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\Appointments\ValueObjects\GuestAddress;
use App\Domains\Appointments\ValueObjects\ManageToken;
use App\Domains\Appointments\ValueObjects\ManageTokenExpiry;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
use App\Shared\Application\UseCaseResponse;
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
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->journal = new AppointmentJournal;
    $this->appointments = new FakeAppointmentRepository($this->journal);
    $this->transactions = new FakeTransactionManager;

    $this->services = (new FakeServiceCatalog($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::serviceSnapshot());
    $this->customers = (new FakeCustomerDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::customerSnapshot());
    $this->staff = (new FakeStaffDirectory($this->journal))
        ->add(FakeBusinessContext::BUSINESS_ID, AppointmentFixtures::staffSnapshot());

    $this->slots = Mockery::mock(BookableSlots::class);
    $this->policies = Mockery::mock(CancellationPolicy::class);
    $this->referenceCodes = Mockery::mock(ReferenceCodeGenerator::class);
    $this->manageTokens = Mockery::mock(ManageTokenFactory::class);

    $this->dispatched = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): array {
        $this->journal->record('events.dispatch');
        $this->dispatched[] = $event;

        return [];
    });

    $this->allowBooking = function (?CancellationRule $rule = null): void {
        $this->slots->shouldReceive('isBookable')->andReturn(true);
        $this->referenceCodes->shouldReceive('next')
            ->andReturn(ReferenceCode::fromString(AppointmentFixtures::REFERENCE_CODE));
        $this->manageTokens->shouldReceive('issue')
            ->andReturn(ManageToken::fromString(AppointmentFixtures::MANAGE_TOKEN));
        $this->policies->shouldReceive('forBusiness')->andReturn($rule ?? CancellationRule::ofMinutes(120));
    };

    $this->useCase = new BookAppointmentAsGuest(
        $this->appointments,
        $this->services,
        $this->customers,
        $this->slots,
        $this->policies,
        new GuestBookingPresenter($this->services, $this->customers, $this->staff),
        $this->referenceCodes,
        $this->manageTokens,
        new FixedIdGenerator(AppointmentFixtures::GENERATED_APPOINTMENT_ID),
        new FakeClock(AppointmentFixtures::now()),
        $this->transactions,
        $this->events,
    );

    $this->book = fn (...$overrides) => $this->useCase->handle(AppointmentFixtures::bookAsGuestInput(...$overrides));
});

describe('a visitor booking as a guest', function () {
    it('answers with the booking the visitor is shown, field by field', function () {
        ($this->allowBooking)();

        $confirmation = ($this->book)()->value();

        expect($confirmation)->toBeInstanceOf(GuestBookingConfirmationData::class)
            ->and($confirmation->booking)->toBeInstanceOf(GuestBookingData::class)
            ->and($confirmation->booking->referenceCode)->toBe(AppointmentFixtures::REFERENCE_CODE)
            ->and($confirmation->booking->customerName)->toBe(AppointmentFixtures::CUSTOMER_NAME)
            ->and($confirmation->booking->serviceName)->toBe(AppointmentFixtures::SERVICE_NAME)
            ->and($confirmation->booking->staffMemberName)->toBe(AppointmentFixtures::STAFF_NAME)
            ->and($confirmation->booking->startsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT))
            ->and($confirmation->booking->endsAt)->toEqual(AppointmentFixtures::instant(AppointmentFixtures::DERIVED_ENDS_AT))
            ->and($confirmation->booking->durationMinutes)->toBe(AppointmentFixtures::SERVICE_DURATION_MINUTES)
            ->and($confirmation->booking->status)->toBe(AppointmentStatus::Booked)
            ->and($confirmation->booking->cancelledAt)->toBeNull()
            ->and($confirmation->booking->cancellationWindowMinutes)->toBe(120)
            ->and($confirmation->booking->changeable)->toBeTrue();
    });

    it('hands the visitor the manage token they will need to come back', function () {
        ($this->allowBooking)();

        expect(($this->book)()->value()->manageToken)->toBe(AppointmentFixtures::MANAGE_TOKEN);
    });

    it('stores only the hash of the manage token, never the token itself', function () {
        ($this->allowBooking)();

        ($this->book)();

        $saved = $this->appointments->saved[0];

        expect($saved->manageTokenHash())
            ->toBe(ManageToken::fromString(AppointmentFixtures::MANAGE_TOKEN)->hash())
            ->and($saved->manageTokenHash())->not->toBe(AppointmentFixtures::MANAGE_TOKEN);
    });

    it('expires the manage token a grace period after the appointment starts', function () {
        ($this->allowBooking)();

        ($this->book)();

        expect($this->appointments->saved[0]->manageTokenExpiresAt())
            ->toEqual(ManageTokenExpiry::forSlot($this->appointments->saved[0]->slot()));
    });

    it('marks the booking as coming from the public page', function () {
        ($this->allowBooking)();

        ($this->book)();

        expect($this->appointments->saved[0]->source())->toBe(BookingSource::Public);
    });

    it('registers the guest against the business the page belongs to', function () {
        ($this->allowBooking)();

        ($this->book)();

        expect($this->customers->guestRegistrations)->toHaveCount(1)
            ->and($this->customers->guestRegistrations[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->customers->guestRegistrations[0]['guest']->name)->toBe(AppointmentFixtures::GUEST_NAME)
            ->and($this->customers->guestRegistrations[0]['guest']->email)->toBe(AppointmentFixtures::GUEST_EMAIL);
    });

    it('saves the appointment inside the transaction it opened', function () {
        ($this->allowBooking)();

        ($this->book)();

        expect($this->transactions->runs())->toBe(1)
            ->and($this->appointments->saved)->toHaveCount(1);
    });

    it('dispatches the booked event exactly once, with the appointment uuid', function () {
        ($this->allowBooking)();

        ($this->book)();

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(AppointmentBooked::class)
            ->and($this->dispatched[0]->id)->toBe(AppointmentFixtures::GENERATED_APPOINTMENT_ID);
    });

    it('dispatches the event only after the transaction has closed', function () {
        ($this->allowBooking)();

        ($this->book)();

        expect($this->journal->entries)->toBe([
            'services.describe',
            'customers.findOrCreateGuest',
            'appointments.save',
            'customers.describe',
            'services.describe',
            'staff.describe',
            'events.dispatch',
        ])->and(array_search('events.dispatch', $this->journal->entries, true))
            ->toBeGreaterThan(array_search('appointments.save', $this->journal->entries, true));
    });
});

describe('what the guest is never shown back', function () {
    it('carries no appointment uuid, so a reservation cannot be enumerated', function () {
        ($this->allowBooking)();

        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(GuestBookingData::class))->getProperties(),
        );

        expect($fields)->not->toContain('id')
            ->and($fields)->not->toContain('appointmentId')
            ->and(($this->book)()->value()->booking)->not->toHaveProperty('id');
    });

    it('carries no customer email, phone or notes', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(GuestBookingData::class))->getProperties(),
        );

        expect($fields)->toBe([
            'referenceCode',
            'customerName',
            'serviceName',
            'staffMemberName',
            'startsAt',
            'endsAt',
            'durationMinutes',
            'status',
            'cancelledAt',
            'cancellationWindowMinutes',
            'changeable',
        ])
            ->and($fields)->not->toContain('customerEmail')
            ->and($fields)->not->toContain('customerPhone')
            ->and($fields)->not->toContain('notes')
            ->and($fields)->not->toContain('customerId')
            ->and($fields)->not->toContain('businessId');
    });

    it('keeps the notes the visitor typed off the confirmation it hands back', function () {
        ($this->allowBooking)();

        $confirmation = ($this->book)(notes: AppointmentFixtures::NOTES)->value();

        expect(json_encode($confirmation->booking, JSON_THROW_ON_ERROR))
            ->not->toContain(AppointmentFixtures::NOTES)
            ->and($this->appointments->saved[0]->notes()?->value)->toBe(AppointmentFixtures::NOTES);
    });

    it('keeps the guest email off the booking it hands back', function () {
        ($this->allowBooking)();

        expect(json_encode(($this->book)()->value()->booking, JSON_THROW_ON_ERROR))
            ->not->toContain(AppointmentFixtures::GUEST_EMAIL);
    });
});

describe('the manage token nobody else issues', function () {
    it('is the one use case that hands a manage token back', function () {
        $carriers = array_filter(
            [
                BookAppointmentAsGuest::class,
                ShowGuestBooking::class,
                RescheduleGuestBooking::class,
                CancelGuestBooking::class,
            ],
            static fn (string $useCase): bool => (string) (new ReflectionMethod($useCase, 'handle'))
                ->getReturnType() === UseCaseResponse::class
                && str_contains(
                    (string) file_get_contents(
                        (string) (new ReflectionClass($useCase))->getFileName(),
                    ),
                    GuestBookingConfirmationData::class,
                ),
        );

        expect(array_values($carriers))->toBe([
            BookAppointmentAsGuest::class,
        ]);
    });

    it('carries the token on the confirmation alone, never on the booking itself', function () {
        $bookingFields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(GuestBookingData::class))->getProperties(),
        );

        expect($bookingFields)->not->toContain('manageToken')
            ->and(array_map(
                static fn (ReflectionProperty $property): string => $property->getName(),
                (new ReflectionClass(GuestBookingConfirmationData::class))->getProperties(),
            ))->toBe(['booking', 'manageToken']);
    });
});

describe('a slot the visitor may not take', function () {
    it('refuses a start the availability engine will not offer', function () {
        $this->slots->shouldReceive('isBookable')->once()->andReturn(false);

        $response = ($this->book)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_slot_not_bookable')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('saves nothing and dispatches nothing when the slot is refused', function () {
        $this->slots->shouldReceive('isBookable')->once()->andReturn(false);

        ($this->book)();

        expect($this->appointments->saved)->toBe([])
            ->and($this->dispatched)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->customers->guestRegistrations)->toBe([]);
    });

    it('asks the availability engine about the business, service, staff member and start', function () {
        $asked = [];

        $this->slots->shouldReceive('isBookable')->once()
            ->andReturnUsing(function (...$arguments) use (&$asked): bool {
                $asked = $arguments;

                return false;
            });

        ($this->book)();

        expect($asked[0])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($asked[1])->toBe(AppointmentFixtures::SERVICE_ID)
            ->and($asked[2])->toBe(AppointmentFixtures::STAFF_ID)
            ->and($asked[3])->toEqual(AppointmentFixtures::instant(AppointmentFixtures::STARTS_AT));
    });

    it('answers with a conflict when the database refuses the overlap', function () {
        ($this->allowBooking)();
        $this->appointments->failingOnSave(AppointmentOverlaps::withAnotherBooking());

        $response = ($this->book)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_overlap')
            ->and($this->dispatched)->toBe([]);
    });
});

describe('the details a guest left', function () {
    it('books a visitor who left nothing but a name', function () {
        ($this->allowBooking)();

        $response = ($this->book)(guest: AppointmentFixtures::guestDetails(email: null));

        $guest = $this->customers->guestRegistrations[0]['guest'] ?? null;

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->booking->customerName)->toBe(AppointmentFixtures::GUEST_NAME)
            ->and($guest?->name)->toBe(AppointmentFixtures::GUEST_NAME)
            ->and($guest?->email)->toBeNull()
            ->and($guest?->phone)->toBeNull()
            ->and($guest?->address)->toBeNull()
            ->and($this->appointments->saved)->toHaveCount(1)
            ->and($this->dispatched)->toHaveCount(1);
    });

    it('registers the name-only guest at the business the booking was made at', function () {
        ($this->allowBooking)();

        ($this->book)(guest: AppointmentFixtures::guestDetails(email: null));

        expect($this->customers->guestRegistrations)->toHaveCount(1)
            ->and($this->customers->guestRegistrations[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID);
    });

    it('hands the address the visitor typed to the customer directory, tidied', function () {
        ($this->allowBooking)();

        ($this->book)(guest: AppointmentFixtures::guestDetails(
            email: null,
            address: AppointmentFixtures::guestAddress(street: '  Av. Reforma 123 ', stateName: ' Nuevo León ', countryCode: 'mx'),
        ));

        $address = $this->customers->guestRegistrations[0]['guest']->address ?? null;

        expect($address)->toBeInstanceOf(GuestAddress::class)
            ->and($address?->street)->toBe('Av. Reforma 123')
            ->and($address?->city)->toBe(AppointmentFixtures::GUEST_CITY)
            ->and($address?->stateName)->toBe('Nuevo León')
            ->and($address?->postalCode)->toBe(AppointmentFixtures::GUEST_POSTAL_CODE)
            ->and($address?->countryCode)->toBe('MX');
    });

    it('refuses an address with no street before it asks any port', function () {
        $response = ($this->book)(guest: AppointmentFixtures::guestDetails(
            address: AppointmentFixtures::guestAddress(street: '   '),
        ));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_guest_address')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->journal->entries)->toBe([])
            ->and($this->appointments->saved)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });

    it('refuses a visitor who left their name blank', function (string $name) {
        $response = ($this->book)(guest: AppointmentFixtures::guestDetails(name: $name));

        expect($response->error()->code)->toBe('invalid_guest_name')
            ->and($this->appointments->saved)->toBe([]);
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'tab' => "\t",
    ]);

    it('refuses a malformed email address', function () {
        expect(($this->book)(guest: AppointmentFixtures::guestDetails(email: 'not-an-email'))->error()->code)
            ->toBe('invalid_guest_email');
    });

    it('refuses a half-written phone number', function () {
        $response = ($this->book)(guest: AppointmentFixtures::guestDetails(
            email: null,
            phoneCountryCode: 'MX',
        ));

        expect($response->error()->code)->toBe('invalid_guest_phone');
    });

    it('accepts a visitor who left a phone but no email', function () {
        ($this->allowBooking)();

        $response = ($this->book)(guest: AppointmentFixtures::guestDetails(
            email: null,
            phoneCountryCode: 'MX',
            phoneNationalNumber: '5512345678',
        ));

        expect($response->succeeded())->toBeTrue()
            ->and($this->customers->guestRegistrations[0]['guest']->phone?->nationalNumber)->toBe('5512345678');
    });

    it('lets a rejection from the customer directory out as a refusal', function () {
        ($this->allowBooking)();
        $this->customers->rejectingGuestWith(InvalidGuestAddress::withoutStreet());

        $response = ($this->book)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_guest_address')
            ->and($this->appointments->saved)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });
});

describe('a business whose doors are shut at the moment the visitor books', function () {
    it('takes a booking for a future slot the availability engine still offers', function () {
        ($this->allowBooking)();

        $response = ($this->book)(startsAt: '2026-06-15T09:00:00+00:00');

        expect($response->succeeded())->toBeTrue()
            ->and($this->appointments->saved)->toHaveCount(1)
            ->and($this->appointments->saved[0]->slot()->startsAt)
            ->toEqual(AppointmentFixtures::instant('2026-06-15T09:00:00+00:00'))
            ->and($this->dispatched)->toHaveCount(1);
    });
});

describe('a payload the use case validates for itself', function () {
    it('refuses a malformed service id before it asks any port', function () {
        $this->slots->shouldReceive('isBookable')->never();

        expect(($this->book)(serviceId: 'not-a-uuid')->error()->code)
            ->toBe('appointment_service_not_found');
    });

    it('refuses a malformed staff member id', function () {
        expect(($this->book)(staffMemberId: 'not-a-uuid')->error()->code)
            ->toBe('appointment_staff_not_found');
    });

    it('refuses a start instant it cannot read', function (string $startsAt) {
        expect(($this->book)(startsAt: $startsAt)->error()->code)
            ->toBe('invalid_appointment_schedule');
    })->with([
        'empty' => '',
        'not a date' => 'tomorrow morning',
        'a date with no time' => '2026-03-10',
    ]);

    it('validates before it touches a single port', function () {
        ($this->book)(serviceId: 'not-a-uuid');

        expect($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0);
    });
});
