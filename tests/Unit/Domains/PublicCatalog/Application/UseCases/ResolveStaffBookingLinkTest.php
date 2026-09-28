<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Application\Dtos\ResolveStaffBookingLinkInput;
use App\Domains\PublicCatalog\Application\Dtos\StaffBookingLinkTarget;
use App\Domains\PublicCatalog\Application\UseCases\ResolveStaffBookingLink;
use App\Domains\PublicCatalog\Contracts\PublishedBusinesses;
use App\Domains\PublicCatalog\Contracts\PublishedStaffLinks;
use App\Domains\PublicCatalog\Contracts\PublishedStaffServices;
use App\Domains\PublicCatalog\Exceptions\BusinessPageNotFound;
use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Shared\Application\UseCaseResponse;
use App\Shared\Contracts\BusinessContext;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;

beforeEach(function () {
    $this->businesses = Mockery::mock(PublishedBusinesses::class);
    $this->staffLinks = Mockery::mock(PublishedStaffLinks::class);
    $this->staffServices = Mockery::mock(PublishedStaffServices::class);

    $this->useCase = new ResolveStaffBookingLink($this->businesses, $this->staffLinks, $this->staffServices);

    $this->resolve = fn (
        string $businessSlug = PublicCatalogFixtures::SLUG,
        string $staffSlug = PublicCatalogFixtures::STAFF_SLUG,
        ?string $serviceSlug = null,
    ): UseCaseResponse => $this->useCase->handle(new ResolveStaffBookingLinkInput($businessSlug, $staffSlug, $serviceSlug));

    $this->businessIsPublished = fn () => $this->businesses->shouldReceive('identifyBySlug')->once()
        ->with(PublicCatalogFixtures::SLUG)
        ->andReturn(PublicCatalogFixtures::BUSINESS_ID);

    $this->staffMemberBooks = fn () => $this->staffLinks->shouldReceive('staffMemberIdFor')->once()
        ->with(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG)
        ->andReturn(PublicCatalogFixtures::TEAM_MEMBER_ID);
});

describe('a staff link that names no service', function () {
    it('points the visitor at the staff member the slug belongs to', function () {
        ($this->businessIsPublished)();
        ($this->staffMemberBooks)();
        $this->staffServices->shouldNotReceive('offeredServiceIdFor');

        $response = ($this->resolve)();
        $target = $response->value();

        expect($response->succeeded())->toBeTrue()
            ->and($target)->toBeInstanceOf(StaffBookingLinkTarget::class)
            ->and($target->businessSlug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($target->staffMemberId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($target->serviceId)->toBeNull();
    });
});

describe('a staff link that names a service', function () {
    it('points the visitor at the staff member and the service they offer', function () {
        ($this->businessIsPublished)();
        ($this->staffMemberBooks)();
        $this->staffServices->shouldReceive('offeredServiceIdFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::TEAM_MEMBER_ID, PublicCatalogFixtures::SERVICE_SLUG)
            ->andReturn(PublicCatalogFixtures::SERVICE_ID);

        $target = ($this->resolve)(serviceSlug: PublicCatalogFixtures::SERVICE_SLUG)->value();

        expect($target->businessSlug)->toBe(PublicCatalogFixtures::SLUG)
            ->and($target->staffMemberId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($target->serviceId)->toBe(PublicCatalogFixtures::SERVICE_ID);
    });

    it('still succeeds, with no service, when the person does not offer it', function () {
        ($this->businessIsPublished)();
        ($this->staffMemberBooks)();
        $this->staffServices->shouldReceive('offeredServiceIdFor')->once()->andReturnNull();

        $response = ($this->resolve)(serviceSlug: 'barba');

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->staffMemberId)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($response->value()->serviceId)->toBeNull();
    });

    it('treats a malformed service slug as a service not offered, never as a failure', function (string $serviceSlug) {
        ($this->businessIsPublished)();
        ($this->staffMemberBooks)();
        $this->staffServices->shouldReceive('offeredServiceIdFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::TEAM_MEMBER_ID, $serviceSlug)
            ->andReturnNull();

        $response = ($this->resolve)(serviceSlug: $serviceSlug);

        expect($response->succeeded())->toBeTrue()
            ->and($response->value()->serviceId)->toBeNull();
    })->with([
        'empty' => '',
        'uppercase' => 'Corte-De-Pelo',
        'an accent' => 'depilación',
        'too long' => str_repeat('a', 61),
    ]);
});

describe('the scope every lookup runs under', function () {
    it('resolves the staff slug inside the business the business slug answered to, by its uuid', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->with('peluqueria-ambar')
            ->andReturn(PublicCatalogFixtures::OTHER_BUSINESS_ID);
        $this->staffLinks->shouldReceive('staffMemberIdFor')->once()
            ->with(PublicCatalogFixtures::OTHER_BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG)
            ->andReturn(PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID);
        $this->staffServices->shouldReceive('offeredServiceIdFor')->once()
            ->with(PublicCatalogFixtures::OTHER_BUSINESS_ID, PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID, PublicCatalogFixtures::SERVICE_SLUG)
            ->andReturn(PublicCatalogFixtures::SECOND_SERVICE_ID);

        $target = ($this->resolve)('peluqueria-ambar', serviceSlug: PublicCatalogFixtures::SERVICE_SLUG)->value();

        expect($target->businessSlug)->toBe('peluqueria-ambar')
            ->and($target->staffMemberId)->toBe(PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID)
            ->and($target->serviceId)->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID);
    });

    it('hands back uuids only, never an internal key', function () {
        ($this->businessIsPublished)();
        ($this->staffMemberBooks)();
        $this->staffServices->shouldReceive('offeredServiceIdFor')->once()->andReturn(PublicCatalogFixtures::SERVICE_ID);

        $target = ($this->resolve)(serviceSlug: PublicCatalogFixtures::SERVICE_SLUG)->value();

        expect($target->staffMemberId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/')
            ->and($target->serviceId)->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/');
    });

    it('carries no business id, because the redirect only needs the slug the url already had', function () {
        $properties = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(StaffBookingLinkTarget::class))->getProperties(),
        );

        expect($properties)->toBe(['businessSlug', 'staffMemberId', 'serviceId'])
            ->and($properties)->not->toContain('businessId');
    });

    it('binds no business context, since a public link is a name and not an entitlement', function () {
        $types = array_map(
            static fn (ReflectionParameter $parameter): string => (string) $parameter->getType(),
            (new ReflectionMethod(ResolveStaffBookingLink::class, '__construct'))->getParameters(),
        );

        expect($types)->toBe([PublishedBusinesses::class, PublishedStaffLinks::class, PublishedStaffServices::class])
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
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('asks nothing about the staff or their services', function () {
        $this->businesses->shouldReceive('identifyBySlug')->once()
            ->andThrow(BusinessPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_SLUG));
        $this->staffLinks->shouldNotReceive('staffMemberIdFor');
        $this->staffServices->shouldNotReceive('offeredServiceIdFor');

        ($this->resolve)(PublicCatalogFixtures::UNKNOWN_SLUG, serviceSlug: PublicCatalogFixtures::SERVICE_SLUG);
    });

    it('refuses a malformed business slug before asking any port', function () {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->staffLinks->shouldNotReceive('staffMemberIdFor');

        $response = ($this->resolve)('Ada Salon');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('business_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });
});

describe('a staff slug no team member books under', function () {
    it('answers with a not found refusal, never a forbidden one that would confirm the slug exists', function () {
        ($this->businessIsPublished)();
        $this->staffLinks->shouldReceive('staffMemberIdFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::UNKNOWN_STAFF_SLUG)
            ->andThrow(StaffBookingPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_STAFF_SLUG));

        $response = ($this->resolve)(staffSlug: PublicCatalogFixtures::UNKNOWN_STAFF_SLUG);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->kind)->not->toBe(DomainFailureKind::Forbidden);
    });

    it('asks nothing about the services of a member it could not find', function () {
        ($this->businessIsPublished)();
        $this->staffLinks->shouldReceive('staffMemberIdFor')->once()
            ->andThrow(StaffBookingPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_STAFF_SLUG));
        $this->staffServices->shouldNotReceive('offeredServiceIdFor');

        ($this->resolve)(staffSlug: PublicCatalogFixtures::UNKNOWN_STAFF_SLUG, serviceSlug: PublicCatalogFixtures::SERVICE_SLUG);
    });

    it('refuses a malformed staff slug before asking any port, not even about the business', function () {
        $this->businesses->shouldNotReceive('identifyBySlug');
        $this->staffLinks->shouldNotReceive('staffMemberIdFor');

        $response = ($this->resolve)(staffSlug: 'Jose Pablo');

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('staff_member_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound);
    });

    it('carries no staff member a caller could read past the refusal', function () {
        ($this->businessIsPublished)();
        $this->staffLinks->shouldReceive('staffMemberIdFor')->once()
            ->andThrow(StaffBookingPageNotFound::withSlug(PublicCatalogFixtures::UNKNOWN_STAFF_SLUG));

        expect(fn () => ($this->resolve)(staffSlug: PublicCatalogFixtures::UNKNOWN_STAFF_SLUG)->value())
            ->toThrow(StaffBookingPageNotFound::class);
    });
});

describe('what is not a refusal', function () {
    it('lets an infrastructure error out, because that is a bug and not a 404', function () {
        $bug = new RuntimeException('the staff_profiles table is gone');

        ($this->businessIsPublished)();
        $this->staffLinks->shouldReceive('staffMemberIdFor')->once()->andThrow($bug);

        expect(fn () => ($this->resolve)())->toThrow($bug);
    });
});
