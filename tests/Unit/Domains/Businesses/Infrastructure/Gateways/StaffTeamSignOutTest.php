<?php

declare(strict_types=1);

use App\Domains\Businesses\Contracts\TeamSignOut;
use App\Domains\Businesses\Infrastructure\Gateways\StaffTeamSignOut;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Contracts\AccountSessions;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeTeamRoster;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->roster = (new FakeTeamRoster)->store(
        StaffFixtures::member(id: StaffFixtures::MEMBER_ID, accountId: StaffFixtures::ACCOUNT_ID, businessId: FakeBusinessContext::BUSINESS_ID, role: StaffRole::Owner),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, businessId: FakeBusinessContext::BUSINESS_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member),
    );

    $this->sessions = new class implements AccountSessions
    {
        /**
         * @var list<list<string>>
         */
        public array $endedAll = [];

        /**
         * @var list<array{accountId: string, keptSessionId: string}>
         */
        public array $endedAllExcept = [];

        public function endAll(array $accountIds): void
        {
            $this->endedAll[] = $accountIds;
        }

        public function endAllExcept(string $accountId, string $keptSessionId): void
        {
            $this->endedAllExcept[] = ['accountId' => $accountId, 'keptSessionId' => $keptSessionId];
        }
    };

    $this->signOut = new StaffTeamSignOut($this->roster, $this->sessions);
});

it('implements the businesses port', function () {
    expect($this->signOut)->toBeInstanceOf(TeamSignOut::class);
});

it('ends the sessions of exactly the accounts on that team, in one call', function () {
    $this->signOut->signOutTeamOf(FakeBusinessContext::BUSINESS_ID);

    expect($this->sessions->endedAll)->toBe([[StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]])
        ->and($this->sessions->endedAllExcept)->toBe([]);
});

it('asks the roster about that business only', function () {
    $this->signOut->signOutTeamOf(FakeBusinessContext::BUSINESS_ID);

    expect($this->roster->teamLookups)->toBe([FakeBusinessContext::BUSINESS_ID]);
});

it('never signs out an account that works only for another business', function () {
    $this->signOut->signOutTeamOf(FakeBusinessContext::BUSINESS_ID);

    expect($this->sessions->endedAll[0])->not->toContain(StaffFixtures::THIRD_ACCOUNT_ID);
});

it('signs out the team of whichever business it is given', function () {
    $this->signOut->signOutTeamOf(StaffFixtures::OTHER_BUSINESS_ID);

    expect($this->sessions->endedAll)->toBe([[StaffFixtures::THIRD_ACCOUNT_ID]]);
});

it('hands an empty list over for a business with nobody on its team', function () {
    $this->signOut->signOutTeamOf('01930000-0000-7000-8000-0000000000b9');

    expect($this->sessions->endedAll)->toBe([[]]);
});
