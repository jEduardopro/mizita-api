<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Domains\PublicCatalog\Infrastructure\Gateways\StaffPublishedStaffLinks;
use App\Domains\Staff\Contracts\BookingSlugRegistry;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Staff\FakeBookingSlugRegistry;

beforeEach(function () {
    $this->registry = (new FakeBookingSlugRegistry)
        ->held(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG, PublicCatalogFixtures::TEAM_MEMBER_ID);

    $this->gateway = new StaffPublishedStaffLinks($this->registry);

    $this->refusalFor = function (string $businessId, string $staffSlug): ?Throwable {
        try {
            $this->gateway->staffMemberIdFor($businessId, $staffSlug);

            return null;
        } catch (Throwable $thrown) {
            return $thrown;
        }
    };
});

describe('a staff slug a team member books under', function () {
    it('answers with the staff uuid of whoever holds the slug', function () {
        expect($this->gateway->staffMemberIdFor(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID);
    });

    it('tells apart two businesses whose members share the same slug', function () {
        $this->registry->held(
            PublicCatalogFixtures::OTHER_BUSINESS_ID,
            PublicCatalogFixtures::STAFF_SLUG,
            PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
        );

        expect($this->gateway->staffMemberIdFor(PublicCatalogFixtures::OTHER_BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toBe(PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID);
    });
});

describe('a staff slug nobody holds', function () {
    it('translates the staff refusal into the public catalog own not found', function () {
        $refusal = ($this->refusalFor)(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::UNKNOWN_STAFF_SLUG);

        expect($refusal)->toBeInstanceOf(StaffBookingPageNotFound::class)
            ->and($refusal->errorCode())->toBe('staff_member_not_found')
            ->and($refusal->kind())->toBe(DomainFailureKind::NotFound)
            ->and($refusal->getPrevious())->toBeInstanceOf(StaffMemberNotFound::class);
    });

    it('names the slug the visitor asked for in the refusal', function () {
        expect(($this->refusalFor)(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::UNKNOWN_STAFF_SLUG)->getMessage())
            ->toContain(PublicCatalogFixtures::UNKNOWN_STAFF_SLUG);
    });

    it('never resolves a slug that only another business hands out', function () {
        expect(($this->refusalFor)(PublicCatalogFixtures::OTHER_BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toBeInstanceOf(StaffBookingPageNotFound::class);
    });

    it('lets an infrastructure error out unchanged, because that is a bug and not a 404', function () {
        $bug = new RuntimeException('the staff_profiles table is gone');
        $registry = Mockery::mock(BookingSlugRegistry::class);
        $registry->shouldReceive('staffMemberIdFor')->once()->andThrow($bug);

        expect(fn () => (new StaffPublishedStaffLinks($registry))
            ->staffMemberIdFor(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toThrow($bug);
    });
});
