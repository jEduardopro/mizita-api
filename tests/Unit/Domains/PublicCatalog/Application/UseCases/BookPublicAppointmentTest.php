<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentSlotNotBookable;
use App\Domains\PublicCatalog\Application\Dtos\BookPublicAppointmentInput;
use App\Domains\PublicCatalog\Application\UseCases\BookPublicAppointment;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\GuestContactFields;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedLocation;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\GuestFieldRequirement;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingRequest;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingStatus;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestAddress;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->contactFields = Mockery::mock(GuestContactFields::class);
    $this->location = Mockery::mock(PublishedLocation::class);
    $this->desk = Mockery::mock(GuestBookingDesk::class);

    $this->useCase = new BookPublicAppointment($this->businesses, $this->contactFields, $this->location, $this->desk);

    $this->book = fn (string $slug = PublicCatalogFixtures::SLUG, ...$overrides): UseCaseResponse => $this->useCase
        ->handle(new BookPublicAppointmentInput($slug, PublicCatalogFixtures::bookingRequest(...$overrides)));

    $this->publishedAt = function (string $businessId = PublicCatalogFixtures::BUSINESS_ID): void {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturn($businessId);
    };

    $this->collecting = function (...$requirements): void {
        $this->contactFields->shouldReceive('forBusiness')->once()
            ->andReturn(PublicCatalogFixtures::contactFields(...$requirements));
    };

    $this->locatedIn = function (?string $countryCode): void {
        $this->location->shouldReceive('forBusiness')->once()->andReturn(
            $countryCode === null ? null : PublicCatalogFixtures::location(countryCode: $countryCode),
        );
    };

    $this->capturedBooking = function (): object {
        $captured = new stdClass;

        $this->desk->shouldReceive('book')->once()
            ->andReturnUsing(function (string $businessId, PublicBookingRequest $request, string $country) use ($captured) {
                $captured->businessId = $businessId;
                $captured->request = $request;
                $captured->country = $country;

                return PublicCatalogFixtures::bookingConfirmation();
            });

        return $captured;
    };

    $this->refusesToBook = function (): void {
        $this->location->shouldNotReceive('forBusiness');
        $this->desk->shouldNotReceive('book');
    };
});

describe('a visitor booking from the public page', function () {
    beforeEach(function () {
        ($this->publishedAt)();
        ($this->collecting)();
        ($this->locatedIn)('MX');
    });

    it('answers with the confirmation the desk handed back, field by field', function () {
        $this->desk->shouldReceive('book')->once()->andReturn(PublicCatalogFixtures::bookingConfirmation());

        $confirmation = ($this->book)()->value();

        expect($confirmation)->toBeInstanceOf(PublicGuestBookingConfirmation::class)
            ->and($confirmation->booking)->toBeInstanceOf(PublicGuestBooking::class)
            ->and($confirmation->booking->referenceCode)->toBe(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($confirmation->booking->customerName)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($confirmation->booking->status)->toBe(PublicBookingStatus::Booked)
            ->and($confirmation->booking->durationMinutes)->toBe(45)
            ->and($confirmation->manageToken)->toBe(PublicCatalogFixtures::MANAGE_TOKEN);
    });

    it('books against the business uuid the slug resolved to, never the slug', function () {
        $captured = ($this->capturedBooking)();

        ($this->book)();

        expect($captured->businessId)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($captured->businessId)->not->toBe(PublicCatalogFixtures::SLUG);
    });

    it('hands the desk the booking the visitor asked for', function () {
        $captured = ($this->capturedBooking)();

        ($this->book)(PublicCatalogFixtures::SLUG, notes: 'Prefiero por la mañana.');

        expect($captured->request->serviceId)->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and($captured->request->staffMemberId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($captured->request->startsAt)->toBe(PublicCatalogFixtures::STARTS_AT)
            ->and($captured->request->guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($captured->request->guest->phoneCountryCode)->toBe(PublicCatalogFixtures::GUEST_PHONE_COUNTRY_CODE)
            ->and($captured->request->guest->phoneNationalNumber)->toBe(PublicCatalogFixtures::GUEST_PHONE_NATIONAL_NUMBER)
            ->and($captured->request->guest->email)->toBe(PublicCatalogFixtures::GUEST_EMAIL)
            ->and($captured->request->notes)->toBe('Prefiero por la mañana.');
    });

    it('books exactly once, so a single submission makes a single appointment', function () {
        $this->desk->shouldReceive('book')->once()->andReturn(PublicCatalogFixtures::bookingConfirmation());

        expect(($this->book)()->succeeded())->toBeTrue();
    });
});

describe('the contact fields the business collects', function () {
    beforeEach(function () {
        ($this->publishedAt)();
    });

    it('asks for the form settings of the business the slug resolved to', function () {
        $askedFor = null;

        $this->contactFields->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($askedFor))
            ->andReturn(PublicCatalogFixtures::contactFields());
        ($this->locatedIn)('MX');
        $this->desk->shouldReceive('book')->once()->andReturn(PublicCatalogFixtures::bookingConfirmation());

        ($this->book)();

        expect($askedFor)->toBe(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('strips every hidden field before the desk ever sees it', function () {
        ($this->collecting)(
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
        );
        ($this->locatedIn)('MX');
        $captured = ($this->capturedBooking)();

        ($this->book)(
            PublicCatalogFixtures::SLUG,
            guest: PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress()),
        );

        expect($captured->request->guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($captured->request->guest->email)->toBeNull()
            ->and($captured->request->guest->phoneCountryCode)->toBeNull()
            ->and($captured->request->guest->phoneNationalNumber)->toBeNull()
            ->and($captured->request->guest->address)->toBeNull();
    });

    it('books a visitor who gave nothing but a name when the business asks for nothing more', function () {
        ($this->collecting)(
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
            GuestFieldRequirement::Hidden,
        );
        ($this->locatedIn)('MX');
        $this->desk->shouldReceive('book')->once()->andReturn(PublicCatalogFixtures::bookingConfirmation());

        $response = ($this->book)(
            PublicCatalogFixtures::SLUG,
            guest: PublicCatalogFixtures::guestDetails(email: null, phoneCountryCode: null, phoneNationalNumber: null),
        );

        expect($response->succeeded())->toBeTrue();
    });

    it('hands the desk the full address a business that collects one was given', function () {
        ($this->collecting)(address: GuestFieldRequirement::Required);
        ($this->locatedIn)('MX');
        $captured = ($this->capturedBooking)();

        ($this->book)(
            PublicCatalogFixtures::SLUG,
            guest: PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress()),
        );

        expect($captured->request->guest->address)->toEqual(PublicCatalogFixtures::guestAddress());
    });

    it('hands the desk no address for an optional one the visitor left blank', function () {
        ($this->collecting)(address: GuestFieldRequirement::Optional);
        ($this->locatedIn)('MX');
        $captured = ($this->capturedBooking)();

        ($this->book)(
            PublicCatalogFixtures::SLUG,
            guest: PublicCatalogFixtures::guestDetails(address: new PublicGuestAddress(null, null, null, null)),
        );

        expect($captured->request->guest->address)->toBeNull();
    });

    it('refuses a visitor who left out a field the business requires, and books nothing', function (array $requirements, array $guestOverrides, string $code) {
        ($this->collecting)(...$requirements);
        ($this->refusesToBook)();

        $response = ($this->book)(PublicCatalogFixtures::SLUG, guest: PublicCatalogFixtures::guestDetails(...$guestOverrides));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    })->with([
        'no phone under the defaults' => [
            [],
            ['phoneCountryCode' => null, 'phoneNationalNumber' => null],
            'missing_guest_phone',
        ],
        'half a phone' => [
            ['phone' => GuestFieldRequirement::Required],
            ['phoneNationalNumber' => null],
            'missing_guest_phone',
        ],
        'no email' => [
            ['email' => GuestFieldRequirement::Required],
            ['email' => null],
            'missing_guest_email',
        ],
        'no address' => [
            ['address' => GuestFieldRequirement::Required],
            ['address' => null],
            'missing_guest_address',
        ],
        'an incomplete address' => [
            ['address' => GuestFieldRequirement::Required],
            ['address' => new PublicGuestAddress(PublicCatalogFixtures::GUEST_STREET, PublicCatalogFixtures::GUEST_CITY, null, null)],
            'missing_guest_address',
        ],
        'a half filled optional address' => [
            ['address' => GuestFieldRequirement::Optional],
            ['address' => new PublicGuestAddress(PublicCatalogFixtures::GUEST_STREET, null, null, null)],
            'missing_guest_address',
        ],
    ]);
});

describe('the country an address is filed under', function () {
    beforeEach(function () {
        ($this->publishedAt)();
        ($this->collecting)(address: GuestFieldRequirement::Required);
        $this->guest = PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress());
    });

    it('is the country of the business the visitor is booking with', function () {
        ($this->locatedIn)('ES');
        $captured = ($this->capturedBooking)();

        ($this->book)(PublicCatalogFixtures::SLUG, guest: $this->guest);

        expect($captured->country)->toBe('ES');
    });

    it('asks for the location of the business the slug resolved to', function () {
        $askedFor = null;

        $this->location->shouldReceive('forBusiness')->once()
            ->with(Mockery::capture($askedFor))
            ->andReturn(PublicCatalogFixtures::location());
        ($this->capturedBooking)();

        ($this->book)(PublicCatalogFixtures::SLUG, guest: $this->guest);

        expect($askedFor)->toBe(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('falls back to Mexico for a business that filed no address', function () {
        ($this->locatedIn)(null);
        $captured = ($this->capturedBooking)();

        ($this->book)(PublicCatalogFixtures::SLUG, guest: $this->guest);

        expect($captured->country)->toBe('MX');
    });
});

describe('what the confirmation may not carry', function () {
    beforeEach(function () {
        $this->businesses->shouldReceive('identifyBySlug')->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->contactFields->shouldReceive('forBusiness')->andReturn(PublicCatalogFixtures::contactFields());
        $this->location->shouldReceive('forBusiness')->andReturn(PublicCatalogFixtures::location());
        $this->desk->shouldReceive('book')->andReturn(PublicCatalogFixtures::bookingConfirmation());
    });

    it('carries no business uuid, customer uuid or appointment uuid', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicGuestBooking::class))->getProperties(),
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
            ->and($fields)->not->toContain('id')
            ->and($fields)->not->toContain('businessId')
            ->and($fields)->not->toContain('customerId')
            ->and($fields)->not->toContain('appointmentId')
            ->and($fields)->not->toContain('customerEmail')
            ->and($fields)->not->toContain('notes');
    });

    it('serializes no tenant identifier at all', function () {
        $serialized = json_encode(($this->book)()->value()->booking, JSON_THROW_ON_ERROR);

        expect($serialized)->not->toContain(PublicCatalogFixtures::BUSINESS_ID)
            ->and($serialized)->not->toContain(PublicCatalogFixtures::SERVICE_ID)
            ->and($serialized)->not->toContain(PublicCatalogFixtures::TEAM_MEMBER_ID);
    });

    it('keeps the notes the visitor typed off the confirmation it hands back', function () {
        $serialized = json_encode(
            ($this->book)(PublicCatalogFixtures::SLUG, notes: 'Prefiero por la mañana.')->value()->booking,
            JSON_THROW_ON_ERROR,
        );

        expect($serialized)->not->toContain('Prefiero por la mañana.');
    });
});

describe('a slug that answers to nothing', function () {
    it('answers with a not found refusal, never a forbidden one', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $response = ($this->book)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden);
    });

    it('asks nothing more and books nothing at all when the slug resolved to nothing', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->contactFields->shouldNotReceive('forBusiness');
        ($this->refusesToBook)();

        expect(($this->book)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('refuses a malformed slug as not found, without asking any port', function (string $slug) {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->contactFields->shouldNotReceive('forBusiness');
        ($this->refusesToBook)();

        expect(($this->book)($slug)->error()->code)->toBe('business_not_found');
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'a path traversal' => '../etc',
        'spaces' => 'ada salon',
    ]);
});

describe('an address out of bounds', function () {
    it('is refused before any port is asked, and nothing is booked', function () {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->contactFields->shouldNotReceive('forBusiness');
        ($this->refusesToBook)();

        $response = ($this->book)(
            PublicCatalogFixtures::SLUG,
            guest: PublicCatalogFixtures::guestDetails(address: PublicCatalogFixtures::guestAddress(
                postalCode: str_repeat('1', PublicGuestAddress::MAXIMUM_POSTAL_CODE_LENGTH + 1),
            )),
        );

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_guest_address')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid);
    });
});

describe('a booking the desk itself refuses', function () {
    beforeEach(function () {
        ($this->publishedAt)();
        ($this->collecting)();
        ($this->locatedIn)('MX');
    });

    it('answers with the refusal the desk raised rather than throwing it', function () {
        $this->desk->shouldReceive('book')->once()
            ->andThrow(AppointmentSlotNotBookable::startingAt(
                new DateTimeImmutable(PublicCatalogFixtures::STARTS_AT),
            ));

        $response = ($this->book)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_slot_not_bookable');
    });

    it('lets an infrastructure error out, because that is a bug and not a verdict', function () {
        $bug = new RuntimeException('the appointments table is gone');

        $this->desk->shouldReceive('book')->once()->andThrow($bug);

        expect(fn () => ($this->book)())->toThrow($bug);
    });
});

describe('the tenant a public booking runs under', function () {
    it('binds no business context, because a public write is resolved from a url segment', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(BookPublicAppointment::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([
            PublishedBusinesses::class,
            GuestContactFields::class,
            PublishedLocation::class,
            GuestBookingDesk::class,
        ])->and($types)->not->toContain(BusinessContext::class);
    });
});
