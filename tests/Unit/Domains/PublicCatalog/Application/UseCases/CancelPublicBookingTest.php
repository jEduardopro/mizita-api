<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentAlreadyCancelled;
use App\Domains\Appointments\Exceptions\AppointmentChangesNotAllowed;
use App\Domains\Appointments\Exceptions\CancellationWindowClosed;
use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use App\Domains\PublicCatalog\Application\Dtos\CancelPublicBookingInput;
use App\Domains\PublicCatalog\Application\UseCases\CancelPublicBooking;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\PublicBookingStatus;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->desk = Mockery::mock(GuestBookingDesk::class);

    $this->useCase = new CancelPublicBooking($this->businesses, $this->desk);

    $this->cancelled = PublicCatalogFixtures::guestBooking(
        status: PublicBookingStatus::Cancelled,
        cancelledAt: '2026-03-01T10:00:00+00:00',
        changeable: false,
    );

    $this->cancel = fn (string $slug = PublicCatalogFixtures::SLUG): UseCaseResponse => $this->useCase
        ->handle(new CancelPublicBookingInput($slug, PublicCatalogFixtures::bookingCredentials()));
});

describe('a visitor cancelling their booking', function () {
    it('answers with the cancelled booking the desk handed back', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('cancel')->once()->andReturn($this->cancelled);

        $booking = ($this->cancel)()->value();

        expect($booking)->toBeInstanceOf(PublicGuestBooking::class)
            ->and($booking->status)->toBe(PublicBookingStatus::Cancelled)
            ->and($booking->cancelledAt)->toEqual(new DateTimeImmutable('2026-03-01T10:00:00+00:00'))
            ->and($booking->changeable)->toBeFalse();
    });

    it('cancels against the business uuid the slug resolved to, never the slug', function () {
        $askedFor = null;
        $credentials = null;

        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('cancel')->once()
            ->with(Mockery::capture($askedFor), Mockery::capture($credentials))
            ->andReturn($this->cancelled);

        ($this->cancel)();

        expect($askedFor)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($askedFor)->not->toBe(PublicCatalogFixtures::SLUG)
            ->and($credentials->referenceCode)->toBe(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($credentials->manageToken)->toBe(PublicCatalogFixtures::MANAGE_TOKEN);
    });

    it('carries no tenant identifier and no manage token back to the visitor', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('cancel')->once()->andReturn($this->cancelled);

        $serialized = json_encode(($this->cancel)()->value(), JSON_THROW_ON_ERROR);

        expect($serialized)->not->toContain(PublicCatalogFixtures::BUSINESS_ID)
            ->and($serialized)->not->toContain(PublicCatalogFixtures::MANAGE_TOKEN);
    });
});

describe('the rules the desk enforces', function () {
    beforeEach(function () {
        $this->businesses->shouldReceive('identifyBySlug')->andReturn(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('answers with appointment_changes_not_allowed when the business forbids cancellation', function () {
        $this->desk->shouldReceive('cancel')->once()->andThrow(AppointmentChangesNotAllowed::byPolicy());

        $response = ($this->cancel)();

        expect($response->error()->code)->toBe('appointment_changes_not_allowed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('answers with cancellation_window_closed when the window has run out', function () {
        $this->desk->shouldReceive('cancel')->once()->andThrow(CancellationWindowClosed::beforeStart());

        $response = ($this->cancel)();

        expect($response->error()->code)->toBe('cancellation_window_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('answers with a conflict for a booking already cancelled', function () {
        $this->desk->shouldReceive('cancel')->once()
            ->andThrow(AppointmentAlreadyCancelled::withId('01930000-0000-7000-8000-0000000000a1'));

        $response = ($this->cancel)();

        expect($response->error()->code)->toBe('appointment_already_cancelled')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('answers with the same not found refusal for any credential that opens nothing', function () {
        $this->desk->shouldReceive('cancel')->once()->andThrow(GuestBookingNotFound::forCredentials());

        $response = ($this->cancel)();

        expect($response->error()->code)->toBe('guest_booking_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('never names the appointment in the refusal it sends back', function () {
        $this->desk->shouldReceive('cancel')->once()->andThrow(GuestBookingNotFound::forCredentials());

        $message = (string) ($this->cancel)()->error()->cause()?->getMessage();

        expect($message)->not->toContain(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($message)->not->toContain(PublicCatalogFixtures::MANAGE_TOKEN)
            ->and($message)->not->toContain(PublicCatalogFixtures::BUSINESS_ID);
    });
});

describe('a slug that answers to nothing', function () {
    it('answers with a not found refusal, never a forbidden one', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $response = ($this->cancel)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden);
    });

    it('cancels nothing when the slug resolved to nothing', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->desk->shouldNotReceive('cancel');

        expect(($this->cancel)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('refuses a malformed slug as not found, without asking any port', function (string $slug) {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->desk->shouldNotReceive('cancel');

        expect(($this->cancel)($slug)->error()->code)->toBe('business_not_found');
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'a path traversal' => '../etc',
    ]);
});

describe('the tenant a public cancellation runs under', function () {
    it('binds no business context, because a public change is resolved from a url segment', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(CancelPublicBooking::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class, GuestBookingDesk::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });
});
