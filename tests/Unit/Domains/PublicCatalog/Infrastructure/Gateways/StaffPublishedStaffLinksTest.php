<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Exceptions\StaffBookingPageNotFound;
use App\Domains\PublicCatalog\Infrastructure\Gateways\StaffPublishedStaffLinks;
use App\Domains\Staff\Application\Services\BookableTeam;
use App\Domains\Staff\Contracts\BookingSlugRegistry;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Staff\FakeBookingSlugRegistry;
use Tests\Support\Staff\FakeTeamAllowance;
use Tests\Support\Staff\FakeTeamOwnership;

beforeEach(function () {
    $this->registry = (new FakeBookingSlugRegistry)
        ->held(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG, PublicCatalogFixtures::TEAM_MEMBER_ID);

    $this->allowance = new FakeTeamAllowance;
    $this->ownership = (new FakeTeamOwnership)
        ->ownedBy(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::TEAM_MEMBER_ID);

    $this->gatewayFor = fn (?BookingSlugRegistry $registry = null): StaffPublishedStaffLinks => new StaffPublishedStaffLinks(
        $registry ?? $this->registry,
        new BookableTeam($this->allowance, $this->ownership),
    );

    $this->gateway = ($this->gatewayFor)();

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

        expect(fn () => ($this->gatewayFor)($registry)
            ->staffMemberIdFor(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toThrow($bug);
    });
});

describe('a staff slug held by a member the plan has paused', function () {
    beforeEach(function () {
        $this->registry->held(
            PublicCatalogFixtures::BUSINESS_ID,
            'grace-hopper',
            PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
        );
        $this->allowance->withoutTeamFor(PublicCatalogFixtures::BUSINESS_ID);
    });

    it('refuses the page with the public catalog own not found', function () {
        $refusal = ($this->refusalFor)(PublicCatalogFixtures::BUSINESS_ID, 'grace-hopper');

        expect($refusal)->toBeInstanceOf(StaffBookingPageNotFound::class)
            ->and($refusal->errorCode())->toBe('staff_member_not_found')
            ->and($refusal->kind())->toBe(DomainFailureKind::NotFound);
    });

    it('refuses exactly as it refuses a slug nobody holds, so a visitor cannot tell a paused member exists', function () {
        $paused = ($this->refusalFor)(PublicCatalogFixtures::BUSINESS_ID, 'grace-hopper');
        $unknown = ($this->refusalFor)(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::UNKNOWN_STAFF_SLUG);

        expect($paused::class)->toBe($unknown::class)
            ->and($paused->errorCode())->toBe($unknown->errorCode())
            ->and($paused->kind())->toBe($unknown->kind())
            ->and($paused->getMessage())->toContain('grace-hopper');
    });

    it('still resolves the owner page, because the owner is bookable on every plan', function () {
        expect($this->gateway->staffMemberIdFor(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID);
    });

    it('refuses the owner page too when the business has no owner on record', function () {
        $this->ownership = new FakeTeamOwnership;

        expect(fn () => ($this->gatewayFor)()->staffMemberIdFor(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toThrow(StaffBookingPageNotFound::class);
    });
});

describe('the plan each business carries', function () {
    beforeEach(function () {
        $this->registry->held(
            PublicCatalogFixtures::OTHER_BUSINESS_ID,
            PublicCatalogFixtures::STAFF_SLUG,
            PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
        );
    });

    it('judges the member against the plan of the business the slug belongs to', function () {
        $this->allowance->withoutTeamFor(PublicCatalogFixtures::BUSINESS_ID);
        $this->ownership->ownedBy(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID);

        expect(($this->refusalFor)(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toBeInstanceOf(StaffBookingPageNotFound::class)
            ->and($this->gateway->staffMemberIdFor(PublicCatalogFixtures::OTHER_BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toBe(PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID)
            ->and($this->allowance->checks)->toBe([PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::OTHER_BUSINESS_ID]);
    });

    it('never lets the owner of another business through as an owner of this one', function () {
        $this->allowance->withoutTeamFor(PublicCatalogFixtures::BUSINESS_ID);
        $this->ownership
            ->ownedBy(PublicCatalogFixtures::OTHER_BUSINESS_ID, PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->ownedBy(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID);

        expect(($this->refusalFor)(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::STAFF_SLUG))
            ->toBeInstanceOf(StaffBookingPageNotFound::class)
            ->and($this->ownership->lookups)->toBe([PublicCatalogFixtures::BUSINESS_ID]);
    });
});
