<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\ServicesPublishedStaffServices;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Services\FakeOfferedServices;
use Tests\Support\Services\ServiceFixtures;

beforeEach(function () {
    $this->offered = new FakeOfferedServices;

    $this->gateway = new ServicesPublishedStaffServices($this->offered);

    $this->find = fn (
        string $serviceSlug = PublicCatalogFixtures::SERVICE_SLUG,
        string $businessId = PublicCatalogFixtures::BUSINESS_ID,
        string $staffMemberId = PublicCatalogFixtures::TEAM_MEMBER_ID,
    ): ?string => $this->gateway->offeredServiceIdFor($businessId, $staffMemberId, $serviceSlug);

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
        $this->offered->store(($this->offeredByAda)());

        expect(($this->find)())->toBe(PublicCatalogFixtures::SERVICE_ID);
    });

    it('picks the one service the slug names among everything the person offers', function () {
        $this->offered->store(
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
        $this->offered->store(($this->offeredByAda)(active: false));

        expect(($this->find)())->toBeNull();
    });

    it('answers with nothing for a slug none of their services carries', function () {
        $this->offered->store(($this->offeredByAda)());

        expect(($this->find)('barba'))->toBeNull();
    });

    it('matches the slug exactly, so a differently cased link is not a match', function () {
        $this->offered->store(($this->offeredByAda)());

        expect(($this->find)('Corte-De-Pelo'))->toBeNull();
    });

    it('answers with nothing for a service offered only by someone else', function () {
        $this->offered->store(($this->offeredByAda)(staffIds: [PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID]));

        expect(($this->find)())->toBeNull();
    });

    it('never lands on another business service that shares the slug', function () {
        $this->offered->store(($this->offeredByAda)(businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID));

        expect(($this->find)())->toBeNull()
            ->and(($this->find)(businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID))->toBe(PublicCatalogFixtures::SERVICE_ID);
    });

    it('answers with nothing for a person who offers no service at all', function () {
        expect(($this->find)())->toBeNull();
    });
});
