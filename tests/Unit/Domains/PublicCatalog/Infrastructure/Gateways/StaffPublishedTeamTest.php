<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\StaffPublishedTeam;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = Mockery::mock(StaffMemberRepository::class);

    $this->teamOf = fn (FakeAccountDirectory $accounts): array => (new StaffPublishedTeam($this->members, $accounts))
        ->forBusiness(PublicCatalogFixtures::BUSINESS_ID);

    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(id: StaffFixtures::ACCOUNT_ID, name: 'Ada Lovelace'),
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper'),
    );

    $this->read = fn (): array => ($this->teamOf)($this->accounts);
});

describe('the team a visitor sees', function () {
    it('publishes each member under the name their account carries', function () {
        $this->members->shouldReceive('allForBusiness')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID)
            ->andReturn([
                StaffFixtures::member(
                    id: PublicCatalogFixtures::TEAM_MEMBER_ID,
                    accountId: StaffFixtures::ACCOUNT_ID,
                    businessId: PublicCatalogFixtures::BUSINESS_ID,
                ),
                StaffFixtures::member(
                    id: PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
                    accountId: StaffFixtures::SECOND_ACCOUNT_ID,
                    businessId: PublicCatalogFixtures::BUSINESS_ID,
                    role: StaffRole::Member,
                ),
            ]);

        $team = ($this->read)();

        expect($team)->toHaveCount(2)
            ->and($team[0])->toBeInstanceOf(PublicTeamMember::class)
            ->and($team[0]->id)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($team[0]->name)->toBe('Ada Lovelace')
            ->and($team[1]->id)->toBe(PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID)
            ->and($team[1]->name)->toBe('Grace Hopper');
    });

    it('identifies a member by their staff uuid, never by the account behind it', function () {
        $this->members->shouldReceive('allForBusiness')->once()->andReturn([
            StaffFixtures::member(
                id: PublicCatalogFixtures::TEAM_MEMBER_ID,
                accountId: StaffFixtures::ACCOUNT_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
        ]);

        $id = ($this->read)()[0]->id;

        expect($id)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($id)->not->toBe(StaffFixtures::ACCOUNT_ID)
            ->and(is_numeric($id))->toBeFalse();
    });

    it('publishes a name and nothing else, so no email or role reaches a visitor', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicTeamMember::class))->getProperties(),
        );

        expect($fields)->toBe(['id', 'name'])
            ->and($fields)->not->toContain('email')
            ->and($fields)->not->toContain('role')
            ->and($fields)->not->toContain('accountId');
    });

    it('asks the directory once, with each account named a single time', function () {
        $this->members->shouldReceive('allForBusiness')->once()->andReturn([
            StaffFixtures::member(
                id: PublicCatalogFixtures::TEAM_MEMBER_ID,
                accountId: StaffFixtures::ACCOUNT_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
            StaffFixtures::member(
                id: PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
                accountId: StaffFixtures::ACCOUNT_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                role: StaffRole::Member,
            ),
        ]);

        ($this->read)();

        expect($this->accounts->callCount())->toBe(1)
            ->and($this->accounts->lastCall())->toBe([StaffFixtures::ACCOUNT_ID]);
    });

    it('leaves out a member whose account the directory cannot describe', function () {
        $this->members->shouldReceive('allForBusiness')->once()->andReturn([
            StaffFixtures::member(
                id: PublicCatalogFixtures::TEAM_MEMBER_ID,
                accountId: StaffFixtures::ACCOUNT_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
            StaffFixtures::member(
                id: PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
                accountId: '01930000-0000-7000-8000-0000000000a9',
                businessId: PublicCatalogFixtures::BUSINESS_ID,
                role: StaffRole::Member,
            ),
        ]);

        expect(array_column(($this->read)(), 'id'))->toBe([PublicCatalogFixtures::TEAM_MEMBER_ID]);
    });

    it('answers with an empty team, and asks the directory nothing, when the business has no staff', function () {
        $this->members->shouldReceive('allForBusiness')->once()->andReturn([]);

        expect(($this->read)())->toBe([])
            ->and($this->accounts->callCount())->toBe(0);
    });

    it('answers with an empty team when the directory knows none of the accounts', function () {
        $this->members->shouldReceive('allForBusiness')->once()->andReturn([
            StaffFixtures::member(
                id: PublicCatalogFixtures::TEAM_MEMBER_ID,
                accountId: StaffFixtures::ACCOUNT_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
        ]);

        expect(($this->teamOf)(new FakeAccountDirectory))->toBe([]);
    });
});
