<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\ServicesPublishedServiceLinks;
use App\Domains\Services\Application\Services\BookableServiceCatalog;
use App\Domains\Services\Contracts\ServiceRepository;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Services\FakeServiceAllowance;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->services = new FakeServiceRepository;

    $this->allowance = FakeServiceAllowance::unlimited();

    $this->gatewayOver = fn (ServiceRepository $services): ServicesPublishedServiceLinks => new ServicesPublishedServiceLinks(
        $services,
        new BookableServiceCatalog($services, $this->allowance),
    );

    $this->lookup = fn (
        string $serviceSlug = PublicCatalogFixtures::SERVICE_SLUG,
        string $businessId = PublicCatalogFixtures::BUSINESS_ID,
    ): ?string => ($this->gatewayOver)($this->services)->bookableServiceIdFor($businessId, $serviceSlug);
});

describe('a service slug the business books under', function () {
    beforeEach(function () {
        $this->services->store(
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                slug: PublicCatalogFixtures::SERVICE_SLUG,
            ),
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SECOND_SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                name: 'Barba',
                slug: 'barba',
            ),
        );
    });

    it('answers with the uuid of the active service holding the slug', function () {
        expect(($this->lookup)())->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and(($this->lookup)('barba'))->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID);
    });

    it('carries the service uuid, never an internal key', function () {
        expect(($this->lookup)())->toBeString()
            ->and(is_numeric(($this->lookup)()))->toBeFalse();
    });

    it('asks the catalogue for the active services of that business alone', function () {
        ($this->lookup)();

        expect(array_values(array_unique($this->services->businessIdsSeen)))->toBe([PublicCatalogFixtures::BUSINESS_ID]);
    });

    it('matches the whole slug, never a prefix of it', function (string $serviceSlug) {
        expect(($this->lookup)($serviceSlug))->toBeNull();
    })->with([
        'a prefix' => 'corte',
        'a longer slug' => 'corte-de-pelo-largo',
        'another case' => 'Corte-De-Pelo',
        'padded' => ' corte-de-pelo ',
    ]);
});

describe('a service slug nothing bookable answers to', function () {
    it('answers with nothing for a slug no service holds', function () {
        $this->services->store(ServiceFixtures::service(
            id: PublicCatalogFixtures::SERVICE_ID,
            businessId: PublicCatalogFixtures::BUSINESS_ID,
        ));

        expect(($this->lookup)('nothing-like-this'))->toBeNull();
    });

    it('answers with nothing for a business with no active service at all', function () {
        expect(($this->lookup)())->toBeNull();
    });

    it('ignores a service the business took off the page', function () {
        $this->services->store(ServiceFixtures::service(
            id: PublicCatalogFixtures::SERVICE_ID,
            businessId: PublicCatalogFixtures::BUSINESS_ID,
            slug: PublicCatalogFixtures::SERVICE_SLUG,
            active: false,
        ));

        expect(($this->lookup)())->toBeNull();
    });

    it('never resolves a slug that only another business offers', function () {
        $this->services->store(ServiceFixtures::service(
            id: PublicCatalogFixtures::SECOND_SERVICE_ID,
            businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID,
            slug: PublicCatalogFixtures::SERVICE_SLUG,
        ));

        expect(($this->lookup)())->toBeNull()
            ->and(($this->lookup)(businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID))
            ->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID);
    });

    it('tells apart two businesses whose services share the same slug', function () {
        $this->services->store(
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                slug: PublicCatalogFixtures::SERVICE_SLUG,
            ),
            ServiceFixtures::service(
                id: PublicCatalogFixtures::SECOND_SERVICE_ID,
                businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID,
                slug: PublicCatalogFixtures::SERVICE_SLUG,
            ),
        );

        expect(($this->lookup)())->toBe(PublicCatalogFixtures::SERVICE_ID)
            ->and(($this->lookup)(businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID))
            ->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID);
    });
});

describe('what is not a miss', function () {
    it('lets an infrastructure error out unchanged, because that is a bug and not a fallback', function () {
        $bug = new RuntimeException('the services table is gone');
        $services = Mockery::mock(ServiceRepository::class);
        $services->shouldReceive('activeForBusiness')->once()->andThrow($bug);

        expect(fn () => ($this->gatewayOver)($services)
            ->bookableServiceIdFor(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::SERVICE_SLUG))
            ->toThrow($bug);
    });
});

describe('a business on the Free plan', function () {
    beforeEach(function () {
        $this->allowance = FakeServiceAllowance::free();
        $this->services->store(...ServiceFixtures::lineup([
            PublicCatalogFixtures::SERVICE_ID,
            PublicCatalogFixtures::SECOND_SERVICE_ID,
            PublicCatalogFixtures::THIRD_SERVICE_ID,
            PublicCatalogFixtures::FOURTH_SERVICE_ID,
        ], PublicCatalogFixtures::BUSINESS_ID));
    });

    it('resolves nothing for an active service beyond the limit', function () {
        expect(($this->lookup)(ServiceFixtures::SLUG.'-4'))->toBeNull();
    });

    it('still resolves the newest service the limit keeps', function () {
        expect(($this->lookup)(ServiceFixtures::SLUG.'-3'))->toBe(PublicCatalogFixtures::THIRD_SERVICE_ID);
    });

    it('asks the plan of the business the link belongs to, and no other', function () {
        ($this->lookup)(ServiceFixtures::SLUG.'-1');

        expect($this->allowance->asked)->toBe([PublicCatalogFixtures::BUSINESS_ID]);
    });

    it('never lets the services of another business use up the places of this one', function () {
        $services = new FakeServiceRepository;
        $services
            ->store(...ServiceFixtures::lineup(
                PublicCatalogFixtures::OTHER_BUSINESS_SERVICE_IDS,
                PublicCatalogFixtures::OTHER_BUSINESS_ID,
            ))
            ->store(ServiceFixtures::service(
                id: PublicCatalogFixtures::SERVICE_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                slug: PublicCatalogFixtures::SERVICE_SLUG,
                createdAt: ServiceFixtures::now()->modify('+30 days'),
            ));

        expect(($this->gatewayOver)($services)->bookableServiceIdFor(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::SERVICE_SLUG))
            ->toBe(PublicCatalogFixtures::SERVICE_ID);
    });
});
