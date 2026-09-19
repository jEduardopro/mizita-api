<?php

declare(strict_types=1);

use App\Domains\Appointments\Exceptions\GuestBookingNotFound;
use App\Domains\PublicCatalog\Application\Dtos\ShowPublicBookingInput;
use App\Domains\PublicCatalog\Application\UseCases\ShowPublicBooking;
use App\Domains\PublicCatalog\Contracts\GuestBookings;
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
    $this->bookings = Mockery::mock(GuestBookings::class);

    $this->useCase = new ShowPublicBooking($this->businesses, $this->bookings);

    $this->show = fn (string $slug = PublicCatalogFixtures::SLUG, ...$overrides): UseCaseResponse => $this->useCase
        ->handle(new ShowPublicBookingInput($slug, PublicCatalogFixtures::bookingCredentials(...$overrides)));
});

describe('a visitor opening their booking link', function () {
    it('answers with the booking the desk found, field by field', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->bookings->shouldReceive('find')->once()->andReturn(PublicCatalogFixtures::guestBooking());

        $booking = ($this->show)()->value();

        expect($booking)->toBeInstanceOf(PublicGuestBooking::class)
            ->and($booking->referenceCode)->toBe(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($booking->customerName)->toBe(PublicCatalogFixtures::GUEST_NAME)
            ->and($booking->status)->toBe(PublicBookingStatus::Booked)
            ->and($booking->cancelledAt)->toBeNull()
            ->and($booking->cancellationWindowMinutes)->toBe(120)
            ->and($booking->changeable)->toBeTrue();
    });

    it('looks the booking up against the business uuid the slug resolved to', function () {
        $askedFor = null;
        $credentials = null;

        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with(PublicCatalogFixtures::SLUG)
            ->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->bookings->shouldReceive('find')->once()
            ->with(Mockery::capture($askedFor), Mockery::capture($credentials))
            ->andReturn(PublicCatalogFixtures::guestBooking());

        ($this->show)();

        expect($askedFor)->toBe(PublicCatalogFixtures::BUSINESS_ID)
            ->and($credentials->referenceCode)->toBe(PublicCatalogFixtures::REFERENCE_CODE)
            ->and($credentials->manageToken)->toBe(PublicCatalogFixtures::MANAGE_TOKEN);
    });

    it('shows a cancelled booking as cancelled rather than hiding it', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->bookings->shouldReceive('find')->once()->andReturn(PublicCatalogFixtures::guestBooking(
            status: PublicBookingStatus::Cancelled,
            cancelledAt: '2026-03-01T10:00:00+00:00',
            changeable: false,
        ));

        $booking = ($this->show)()->value();

        expect($booking->status)->toBe(PublicBookingStatus::Cancelled)
            ->and($booking->changeable)->toBeFalse();
    });

    it('carries no tenant identifier back to the visitor', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->bookings->shouldReceive('find')->once()->andReturn(PublicCatalogFixtures::guestBooking());

        $serialized = json_encode(($this->show)()->value(), JSON_THROW_ON_ERROR);

        expect($serialized)->not->toContain(PublicCatalogFixtures::BUSINESS_ID)
            ->and($serialized)->not->toContain(PublicCatalogFixtures::SERVICE_ID)
            ->and($serialized)->not->toContain(PublicCatalogFixtures::MANAGE_TOKEN);
    });
});

describe('credentials that do not open a booking', function () {
    it('answers with the not found refusal the desk raised', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->bookings->shouldReceive('find')->once()->andThrow(GuestBookingNotFound::forCredentials());

        $response = ($this->show)();

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('guest_booking_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('answers identically whichever credential was wrong', function (string $referenceCode, string $manageToken) {
        $this->businesses->shouldReceive('identifyBySlug')->andReturn(PublicCatalogFixtures::BUSINESS_ID);
        $this->bookings->shouldReceive('find')->andThrow(GuestBookingNotFound::forCredentials());

        $error = ($this->show)(
            PublicCatalogFixtures::SLUG,
            referenceCode: $referenceCode,
            manageToken: $manageToken,
        )->error();

        expect($error->code)->toBe('guest_booking_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'unknown code' => ['Z9Y8X7W6', PublicCatalogFixtures::MANAGE_TOKEN],
        'wrong token' => [PublicCatalogFixtures::REFERENCE_CODE, str_repeat('f', 64)],
        'both wrong' => ['Z9Y8X7W6', str_repeat('f', 64)],
    ]);
});

describe('a slug that answers to nothing', function () {
    it('answers with a not found refusal, never a forbidden one', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $response = ($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden);
    });

    it('never looks a booking up when the slug resolved to nothing', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->bookings->shouldNotReceive('find');

        expect(($this->show)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('refuses a malformed slug as not found, without asking any port', function (string $slug) {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->bookings->shouldNotReceive('find');

        expect(($this->show)($slug)->error()->code)->toBe('business_not_found');
    })->with([
        'empty' => '',
        'uppercase' => 'Ada-Salon',
        'a path traversal' => '../etc',
    ]);
});

describe('the tenant a public read runs under', function () {
    it('binds no business context, because a public read is resolved from a url segment', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ShowPublicBooking::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class, GuestBookings::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });

    it('reads through a port that declares a read and nothing else', function () {
        $methods = array_map(
            static fn (ReflectionMethod $method): string => $method->getName(),
            (new ReflectionClass(GuestBookings::class))->getMethods(),
        );

        expect($methods)->toBe(['find'])
            ->and((string) (new ReflectionMethod(GuestBookings::class, 'find'))->getReturnType())
            ->toBe(PublicGuestBooking::class);
    });
});
