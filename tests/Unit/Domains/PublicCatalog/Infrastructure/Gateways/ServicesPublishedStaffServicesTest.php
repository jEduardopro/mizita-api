<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\ServicesPublishedStaffServices;
use App\Domains\Services\Application\Services\BookableServiceCatalog;
use App\Domains\Services\Entities\Service;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Services\FakeOfferedServices;
use Tests\Support\Services\FakeServiceAllowance;
use Tests\Support\Services\FakeServiceRepository;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->offered = new FakeOfferedServices;
    $this->services = new FakeServiceRepository;
    $this->allowance = FakeServiceAllowance::unlimited();

    $this->stock = function (Service ...$services): void {
        $this->offered->store(...$services);
        $this->services->store(...$services);
    };

    $this->find = fn (
        string $serviceSlug = PublicCatalogFixtures::SERVICE_SLUG,
        string $businessId = PublicCatalogFixtures::BUSINESS_ID,
        string $staffMemberId = PublicCatalogFixtures::TEAM_MEMBER_ID,
    ): ?string => (new ServicesPublishedStaffServices(
        $this->offered,
        new BookableServiceCatalog($this->services, $this->allowance),
    ))->offeredServiceIdFor($businessId, $staffMemberId, $serviceSlug);

    $this->offeredByAda = fn (
        string $id = PublicCatalogFixtures::SERVICE_ID,
        string $slug = PublicCatalogFixtures::SERVICE_SLUG,
        bool $active = true,
        string $businessId = PublicCatalogFixtures::BUSINESS_ID,
        array $staffIds = [PublicCatalogFixtures::TEAM_MEMBER_ID],
    ) => ServiceFixtures::service(
        id: $id,
        businessId: $businessId,
        slug: $slug,
        active: $active,
        staffIds: $staffIds,
    );
});

describe('a service the person offers', function () {
    it('answers with the uuid of the active service the slug names', function () {
        ($this->stock)(($this->offeredByAda)());

        expect(($this->find)())->toBe(PublicCatalogFixtures::SERVICE_ID);
    });

    it('picks the one service the slug names among everything the person offers', function () {
        ($this->stock)(
            ($this->offeredByAda)(id: PublicCatalogFixtures::SERVICE_ID, slug: 'barba'),
            ($this->offeredByAda)(id: PublicCatalogFixtures::SECOND_SERVICE_ID, slug: PublicCatalogFixtures::SERVICE_SLUG),
        );

        expect(($this->find)())->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID);
    });

    it('asks only for what that person offers inside that business', function () {
        ($this->find)();

        expect($this->offered->calls)->toBe([[
            'businessId' => PublicCatalogFixtures::BUSINESS_ID,
            'staffId' => PublicCatalogFixtures::TEAM_MEMBER_ID,
        ]]);
    });
});

describe('a service the link cannot land on', function () {
    it('answers with nothing for a service the business took off the page', function () {
        ($this->stock)(($this->offeredByAda)(active: false));

        expect(($this->find)())->toBeNull();
    });

    it('answers with nothing for a slug none of their services carries', function () {
        ($this->stock)(($this->offeredByAda)());

        expect(($this->find)('barba'))->toBeNull();
    });

    it('matches the slug exactly, so a differently cased link is not a match', function () {
        ($this->stock)(($this->offeredByAda)());

        expect(($this->find)('Corte-De-Pelo'))->toBeNull();
    });

    it('answers with nothing for a service offered only by someone else', function () {
        ($this->stock)(($this->offeredByAda)(staffIds: [PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID]));

        expect(($this->find)())->toBeNull();
    });

    it('never lands on another business service that shares the slug', function () {
        ($this->stock)(($this->offeredByAda)(businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID));

        expect(($this->find)())->toBeNull()
            ->and(($this->find)(businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID))->toBe(PublicCatalogFixtures::SERVICE_ID);
    });

    it('answers with nothing for a person who offers no service at all', function () {
        expect(($this->find)())->toBeNull();
    });
});

describe('a business on the Free plan', function () {
    beforeEach(function () {
        $this->allowance = FakeServiceAllowance::free();
        ($this->stock)(...array_map(
            static fn (string $id, int $position): Service => ServiceFixtures::service(
                id: $id,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                slug: ServiceFixtures::SLUG.'-'.($position + 1),
                staffIds: [PublicCatalogFixtures::TEAM_MEMBER_ID],
                createdAt: ServiceFixtures::now()->modify("+{$position} days"),
            ),
            [
                PublicCatalogFixtures::FOURTH_SERVICE_ID,
                PublicCatalogFixtures::THIRD_SERVICE_ID,
                PublicCatalogFixtures::SECOND_SERVICE_ID,
                PublicCatalogFixtures::SERVICE_ID,
            ],
            [0, 1, 2, 3],
        ));
    });

    it('answers with nothing for an offered service beyond the limit, judged by age and never by id', function () {
        expect(($this->find)(ServiceFixtures::SLUG.'-4'))->toBeNull();
    });

    it('still answers for the newest offered service the limit keeps', function () {
        expect(($this->find)(ServiceFixtures::SLUG.'-3'))->toBe(PublicCatalogFixtures::SECOND_SERVICE_ID);
    });

    it('asks the plan of the business the link belongs to, and no other', function () {
        ($this->find)(ServiceFixtures::SLUG.'-1');

        expect($this->allowance->asked)->toBe([PublicCatalogFixtures::BUSINESS_ID]);
    });
});

describe('the limit of each business', function () {
    it('never lets the services of another business use up the places of this one', function () {
        $this->allowance = FakeServiceAllowance::free();
        $this->services->store(...ServiceFixtures::lineup(
            PublicCatalogFixtures::OTHER_BUSINESS_SERVICE_IDS,
            PublicCatalogFixtures::OTHER_BUSINESS_ID,
        ));
        ($this->stock)(ServiceFixtures::service(
            id: PublicCatalogFixtures::SERVICE_ID,
            businessId: PublicCatalogFixtures::BUSINESS_ID,
            slug: PublicCatalogFixtures::SERVICE_SLUG,
            staffIds: [PublicCatalogFixtures::TEAM_MEMBER_ID],
            createdAt: ServiceFixtures::now()->modify('+30 days'),
        ));

        expect(($this->find)())->toBe(PublicCatalogFixtures::SERVICE_ID);
    });
});
