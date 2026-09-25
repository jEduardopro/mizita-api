<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\ListTeamMembersInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Application\UseCases\ListTeamMembers;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Domains\Staff\ValueObjects\TeamSort;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use App\Shared\ValueObjects\SortDirection;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffPhoneBook;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\FakeTeamRoster;
use Tests\Support\Staff\FakeTeamTemporaryPasswords;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->roster = (new FakeTeamRoster)->store(
        StaffFixtures::member(),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
        StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::THIRD_ACCOUNT_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID),
    );
    $this->phones = (new FakeStaffPhoneBook)->store(StaffFixtures::PROFILE_ID, PhoneNumbers::mexican());
    $this->profiles = (new FakeStaffProfileRepository)->store(StaffFixtures::profile());
    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(),
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'),
        StaffFixtures::account(id: StaffFixtures::THIRD_ACCOUNT_ID, name: 'Linus Pauling', email: 'linus@example.com'),
    );

    $this->build = fn (?FakeBusinessContext $business = null): ListTeamMembers => new ListTeamMembers(
        $this->roster,
        $this->phones,
        new TeamMemberPresenter($this->accounts, $this->profiles, $this->phones, new FakeStaffProfilePhotos, new FakeTeamTemporaryPasswords),
        $business ?? new FakeBusinessContext,
    );

    $this->list = fn (array $query = [], ?ListTeamMembers $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(ListTeamMembersInput::fromRequest($query));
});

it('answers with a page of described members, keeping the total and the pagination', function () {
    $page = ($this->list)(['page' => '1', 'per_page' => '1'])->value();

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0])->toBeInstanceOf(TeamMemberData::class)
        ->and($page->items[0]->id)->toBe(StaffFixtures::MEMBER_ID)
        ->and($page->items[0]->name)->toBe('Ada Lovelace')
        ->and($page->items[0]->phone?->e164())->toBe(PhoneNumbers::MX_E164)
        ->and($page->items[0]->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
        ->and($page->total)->toBe(2)
        ->and($page->pagination->page)->toBe(1)
        ->and($page->pagination->perPage)->toBe(1)
        ->and($page->lastPage())->toBe(2);
});

it('hands the roster the query the input asked for', function () {
    ($this->list)(['search' => 'grace', 'sort' => 'created_at', 'direction' => 'desc', 'page' => '2', 'per_page' => '5']);

    $query = $this->roster->searches[0]['query'];

    expect($this->roster->searches)->toHaveCount(1)
        ->and($query->search?->raw())->toBe('grace')
        ->and($query->sort)->toBe(TeamSort::CreatedAt)
        ->and($query->direction)->toBe(SortDirection::Descending)
        ->and($query->pagination->page)->toBe(2)
        ->and($query->pagination->perPage)->toBe(5);
});

it('widens a search to the profiles whose phone matches it', function () {
    $this->phones->matching('5512', StaffFixtures::PROFILE_ID);

    ($this->list)(['search' => '5512']);

    expect($this->phones->numberSearches)->toBe(['5512'])
        ->and($this->roster->searches[0]['query']->profileIdsMatchingPhone)->toBe([StaffFixtures::PROFILE_ID]);
});

it('never searches phones when nothing was searched for', function () {
    ($this->list)();

    expect($this->phones->numberSearches)->toBe([])
        ->and($this->roster->searches[0]['query']->search)->toBeNull()
        ->and($this->roster->searches[0]['query']->profileIdsMatchingPhone)->toBe([]);
});

it('answers an empty page for a team with nobody on it', function () {
    $page = ($this->list)(useCase: ($this->build)(new FakeBusinessContext('01930000-0000-7000-8000-0000000000b9')))->value();

    expect($page->items)->toBe([])
        ->and($page->total)->toBe(0);
});

it('refuses a search past the limit without searching anything', function () {
    $response = ($this->list)(['search' => str_repeat('a', 121)]);

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('invalid_team_search')
        ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
        ->and($this->roster->searches)->toBe([])
        ->and($this->phones->numberSearches)->toBe([]);
});

describe('tenant isolation', function () {
    it('searches only the team of the business in context', function () {
        $page = ($this->list)()->value();

        expect($this->roster->searches[0]['businessId'])->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and(array_map(static fn (TeamMemberData $member): string => $member->id, $page->items))
            ->toBe([StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID]);
    });

    it('lists the other business team when that is the business in context', function () {
        $page = ($this->list)(useCase: ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)))->value();

        expect($this->roster->searches[0]['businessId'])->toBe(StaffFixtures::OTHER_BUSINESS_ID)
            ->and(array_map(static fn (TeamMemberData $member): string => $member->id, $page->items))
            ->toBe([StaffFixtures::THIRD_MEMBER_ID])
            ->and($page->items[0]->jobTitle)->toBeNull();
    });
});
