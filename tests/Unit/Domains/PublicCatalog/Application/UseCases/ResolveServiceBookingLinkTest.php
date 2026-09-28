<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\ResolveServiceBookingLinkInput;
use App\Domains\PublicCatalog\Application\Dtos\ServiceBookingLinkTarget;
use App\Domains\PublicCatalog\Application\UseCases\ResolveServiceBookingLink;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedServiceLinks;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->serviceLinks = Mockery::mock(PublishedServiceLinks::class);

    $this->useCase = new ResolveServiceBookingLink($this->businesses, $this->serviceLinks);

    $this->resolve = fn (
        string $businessSlug = PublicCatalogFixtures::SLUG,
        string $serviceSlug = PublicCatalogFixtures::SERVICE_SLUG,
    ): UseCaseResponse => $this->useCase->handle(new ResolveServiceBookingLinkInput($businessSlug, $serviceSlug));

    $this->businessIsPublished = fn () => $this->businesses->shouldReceive('identifyBySlug')->once()
        ->with(PublicCatalogFixtures::SLUG)
        ->andReturn(PublicCatalogFixtures::BUSINESS_ID);
});

describe('a service link the business offers', function () {
    it('points the visitor at the service the slug belongs to', function () {
        ($this->businessIsPublished)();
        $this->serviceLinks->shouldReceive('bookableServiceIdFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::SERVICE_SLUG)
            ->andReturn(PublicCatalogFixtures::SERVICE_ID);

        $response = ($this->resolve)();
        $target = $response->value();

        expect($response->succeeded())->toBeTrue()
            ->and($target)->toBeInstanceOf(ServiceBookingLinkTarget::class)
            ->and($target->businessSlug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($target->serviceId)->toBe(PublicCatalogFixtures::SERVICE_ID);
    });

    it('hands back the service uuid, never an internal key', function () {
        ($this->businessIsPublished)();
        $this->serviceLinks->shouldReceive('bookableServiceIdFor')->once()->andReturn(PublicCatalogFixtures::SERVICE_ID);

        expect(($this->resolve)()->value()->serviceId)
            ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });
});

describe('a service link the business does not offer', function () {
    it('still succeeds, with no service, so the visitor lands on the business page', function () {
        ($this->businessIsPublished)();
        $this->serviceLinks->shouldReceive('bookableServiceIdFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, 'barba')
            ->andReturnNull();

        $response = ($this->resolve)(serviceSlug: 'barba');

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->businessSlug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($response->value()->serviceId)->toBeNull();
    });

    it('treats a malformed service slug as a service not offered, never as a failure', function (string $serviceSlug) {
        ($this->businessIsPublished)();
        $this->serviceLinks->shouldReceive('bookableServiceIdFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, $serviceSlug)
            ->andReturnNull();

        $response = ($this->resolve)(serviceSlug: $serviceSlug);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->serviceId)->toBeNull();
    })->with([
        'empty' => '',
        'spaces' => '   ',
        'uppercase' => 'Corte-De-Pelo',
        'an accent' => 'depilación',
        'too long' => str_repeat('a', 61),
    ]);
});

describe('the scope every lookup runs under', function () {
    it('resolves the service slug inside the business the business slug answered to, by its uuid', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with('peluqueria-ambar')
            ->andReturn(PublicCatalogFixtures::OTHER_BUSINESS_ID);
        $this->serviceLinks->shouldReceive('bookableServiceIdFor')->once()
            ->with(PublicCatalogFixtures::OTHER_BUSINESS_ID, PublicCatalogFixtures::SERVICE_SLUG)
            ->andReturn(PublicCatalogFixtures::SECOND_SERVICE_ID);

        $target = ($this->resolve)('peluqueria-ambar')->value();

        expect($target->businessSlug)->toBe('peluqueria-ambar')
            ->and($target->serviceId)->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID);
    });

    it('carries no business id, because the redirect only needs the slug the url already had', function () {
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(ServiceBookingLinkTarget::class))->getProperties(),
        );

        expect($properties)->toBe(['businessSlug', 'serviceId'])
            ->and($properties)->not->toContain('businessId');
    });

    it('binds no business context, since a public link is a name and not an entitlement', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ResolveServiceBookingLink::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class, PublishedServiceLinks::class])
            ->and($types)->not->toContain(BusinessContext::class);
    });
});

describe('a business slug no published business answers to', function () {
    it('answers with a not found refusal instead of throwing it', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with(PublicCatalogFixtures::UNKNOWN_SLUG)
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        $response = ($this->resolve)(PublicCatalogFixtures::UNKNOWN_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden);
    });

    it('asks nothing about the services of a business it could not find', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->serviceLinks->shouldNotReceive('bookableServiceIdFor');

        expect(($this->resolve)(PublicCatalogFixtures::UNKNOWN_SLUG)->failed())->toBeTrue();
    });

    it('refuses a malformed business slug before asking any port', function (string $businessSlug) {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->serviceLinks->shouldNotReceive('bookableServiceIdFor');

        $response = ($this->resolve)($businessSlug);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    })->with([
        'empty' => '',
        'a space' => 'Ada Salon',
        'uppercase' => 'Ada-Salon',
        'an accent' => 'peluquería',
        'too long' => str_repeat('a', 61),
    ]);

    it('carries no target a caller could read past the refusal', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));

        expect(fn () => ($this->resolve)(PublicCatalogFixtures::UNKNOWN_SLUG)->value())
            ->toThrow(BusinessPageNotFound::class);
    });
});

describe('what is not a refusal', function () {
    it('lets an infrastructure error out, because that is a bug and not a 404', function () {
        $bug = new RuntimeException('the services table is gone');

        ($this->businessIsPublished)();
        $this->serviceLinks->shouldReceive('bookableServiceIdFor')->once()->andThrow($bug);

        expect(fn () => ($this->resolve)())->toThrow($bug);
    });
});
