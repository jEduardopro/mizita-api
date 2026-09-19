<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\AppointmentChangesNotAllowed;
use App\Domains\Appointments\Exceptions\CancellationWindowClosed;
use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use App\Domains\PublicCatalog\Application\Dtos\ReschedulePublicBookingInput;
use App\Domains\PublicCatalog\Application\UseCases\ReschedulePublicBooking;
use App\Domains\PublicCatalog\Contracts\GuestBookingDesk;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\ValueObjects\PublicGuestBooking;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

const RESCHEDULED_TO = '2026-03-12T11:00:00+00:00';

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->desk = Mockery::mock(GuestBookingDesk::class);

    $this->useCase = new ReschedulePublicBooking($this->businesses, $this->desk);

    $this->reschedule = fn (
        string $slug = PublicCatalogFixtures::SLUG,
        string $startsAt = RESCHEDULED_TO,
    ): UseCaseResponse => $this->useCase->handle(new ReschedulePublicBookingInput(
        slug: $slug,
        credentials: PublicCatalogFixtures::bookingCredentials(),
        startsAt: $startsAt,
    ));
});

describe('a visitor moving their booking', function () {
    it('answers with the moved booking the desk handed back', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('reschedule')->once()->andReturn(PublicCatalogFixtures::guestBooking());

        $booking = ($this->reschedule)()->value();

        expect($booking)->toBeInstanceOf(PublicGuestBooking::class)
            ->and($booking->referenceCode)->toBe(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($booking->changeable)->toBeTrue();
    });

    it('hands the desk the business uuid, the credentials and the new start', function () {
        $askedFor = null;
        $credentials = null;
        $startsAt = null;

        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('reschedule')->once()
            ->with(Mockery::capture($askedFor), Mockery::capture($credentials), Mockery::capture($startsAt))
            ->andReturn(PublicCatalogFixtures::guestBooking());

        ($this->reschedule)();

        expect($askedFor)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($askedFor)->not->toBe(PublicCatalogFixtures::SLUG)
            ->and($credentials->referenceCode)->toBe(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($startsAt)->toBe(RESCHEDULED_TO);
    });

    it('hands back no manage token, because the visitor already holds it', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('reschedule')->once()->andReturn(PublicCatalogFixtures::guestBooking());

        $booking = ($this->reschedule)()->value();

        expect($booking)->not->toHaveProperty('manageToken')
            ->and(json_encode($booking, JSON_THROW_ON_ERROR))->not->toContain(PublicCatalogFixtures::MANAGE_TOKEN);
    });

    it('carries no tenant identifier back to the visitor', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->desk->shouldReceive('reschedule')->once()->andReturn(PublicCatalogFixtures::guestBooking());

        expect(json_encode(($this->reschedule)()->value(), JSON_THROW_ON_ERROR))
            ->not->toContain(PublicCatalogFixtures::BUSINESS_ID);
    });
});

describe('the rules the desk enforces', function () {
    beforeEach(function () {
        $this->businesses->shouldReceive('identifyBySlug')->andReturn(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('answers with appointment_changes_not_allowed when the business forbids changes', function () {
        $this->desk->shouldReceive('reschedule')->once()->andThrow(AppointmentChangesNotAllowed::byPolicy());

        $response = ($this->reschedule)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('appointment_changes_not_allowed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden);
    });

    it('answers with cancellation_window_closed when the window has run out', function () {
        $this->desk->shouldReceive('reschedule')->once()->andThrow(CancellationWindowClosed::beforeStart());

        $response = ($this->reschedule)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('cancellation_window_closed')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict);
    });

    it('keeps the two refusals distinguishable at the public edge', function () {
        $this->desk->shouldReceive('reschedule')->once()->andThrow(AppointmentChangesNotAllowed::byPolicy());
        $forbidden = ($this->reschedule)()->error();

        $this->desk->shouldReceive('reschedule')->once()->andThrow(CancellationWindowClosed::beforeStart());
        $closed = ($this->reschedule)()->error();

        expect($forbidden->code)->not->toBe($closed->code)
            ->and($forbidden->kind)->not->toBe($closed->kind);
    });

    it('answers with the same not found refusal for any credential that opens nothing', function () {
        $this->desk->shouldReceive('reschedule')->once()->andThrow(GuestBookingNotFound::forCredentials());

        $response = ($this->reschedule)();

        expect($response->error()->code)->toBe('guest_booking_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });
});

describe('a slug that answers to nothing', function () {
    it('answers with a not found refusal, never a forbidden one', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $response = ($this->reschedule)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden);
    });

    it('moves nothing when the slug resolved to nothing', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->desk->shouldNotReceive('reschedule');

        expect(($this->reschedule)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('refuses a malformed slug as not found, without asking any port', function (string $slug) {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->desk->shouldNotReceive('reschedule');

        expect(($this->reschedule)($slug)->error()->code)->toBe('business_not_found');
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'a path traversal' => '../etc',
    ]);
});

describe('the tenant a public change runs under', function () {
    it('binds no business context, because a public change is resolved from a url segment', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ReschedulePublicBooking::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class, GuestBookingDesk::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });
});
