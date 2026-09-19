<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentSlotNotBookable;
use App\Domains\PublicCatalog\Application\Dtos\BookPublicAppointmentInput;
use App\Domains\PublicCatalog\Application\UseCases\BookPublicAppointment;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingStatus;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBookingConfirmation;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->desk = Mockery::mock(GuestBookingDesk::class);

    $this->useCase = new BookPublicAppointment($this->businesses, $this->desk);

    $this->book = fn (string $slug = PublicCatalogFixtures::SLUG, ...$overrides): UseCaseResponse => $this->useCase
        ->handle(new BookPublicAppointmentInput($slug, PublicCatalogFixtures::bookingRequest(...$overrides)));
});

describe('a visitor booking from the public page', function () {
    it('answers with the confirmation the desk handed back, field by field', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
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
        $askedFor = null;

        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('book')->once()
            ->with(Mockery::capture($askedFor), Mockery::any())
            ->andReturn(PublicCatalogFixtures::bookingConfirmation());

        ($this->book)();

        expect($askedFor)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($askedFor)->not->toBe(PublicCatalogFixtures::SLUG);
    });

    it('hands the desk the booking the visitor asked for, untouched', function () {
        $request = null;

        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('book')->once()
            ->with(Mockery::any(), Mockery::capture($request))
            ->andReturn(PublicCatalogFixtures::bookingConfirmation());

        ($this->book)(PublicCatalogFixtures::SLUG, notes: 'Prefiero por la mañana.');

        expect($request->serviceId)->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and($request->staffMemberId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($request->startsAt)->toBe(PublicCatalogFixtures::STARTS_AT)
            ->and($request->guest->name)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($request->notes)->toBe('Prefiero por la mañana.');
    });

    it('books exactly once, so a single submission makes a single appointment', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('book')->once()->andReturn(PublicCatalogFixtures::bookingConfirmation());

        ($this->book)();
    });
});

describe('what the confirmation may not carry', function () {
    beforeEach(function () {
        $this->businesses->shouldReceive('identifyBySlug')->andReturn(PublicCatalogFixtures::BUSINESS_ID);
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

    it('books nothing at all when the slug resolved to nothing', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->desk->shouldNotReceive('book');

        expect(($this->book)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('refuses a malformed slug as not found, without asking any port', function (string $slug) {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->desk->shouldNotReceive('book');

        expect(($this->book)($slug)->error()->code)->toBe('business_not_found');
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'a path traversal' => '../etc',
        'spaces' => 'ada salon',
    ]);
});

describe('a booking the desk itself refuses', function () {
    it('answers with the refusal the desk raised rather than throwing it', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
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

        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
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

        expect($types)->toBe([PublishedBusinesses::class, GuestBookingDesk::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });
});
