<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;
use Tests\Support\FakeBusinessContext;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffPhoneBook;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\FakeTeamTemporaryPasswords;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(),
        StaffFixtures::account(
            id: StaffFixtures::SECOND_ACCOUNT_ID,
            name: 'Grace Hopper',
            email: 'grace@example.com',
            awaitingPasswordChange: true,
        ),
    );
    $this->profiles = (new FakeStaffProfileRepository)->store(
        StaffFixtures::profile(),
        StaffFixtures::profile(
            id: StaffFixtures::SECOND_PROFILE_ID,
            staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
            jobTitle: null,
            about: null,
        ),
    );
    $this->phones = (new FakeStaffPhoneBook)->store(StaffFixtures::PROFILE_ID, PhoneNumbers::mexican());
    $this->photos = (new FakeStaffProfilePhotos)->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);
    $this->temporaryPasswords = new FakeTeamTemporaryPasswords;

    $this->presenter = new TeamMemberPresenter($this->accounts, $this->profiles, $this->phones, $this->photos, $this->temporaryPasswords);

    $this->owner = StaffFixtures::member();
    $this->invitee = StaffFixtures::member(
        id: StaffFixtures::SECOND_MEMBER_ID,
        accountId: StaffFixtures::SECOND_ACCOUNT_ID,
        role: StaffRole::Member,
        createdAt: new DateTimeImmutable('2026-02-01T09:00:00+00:00'),
    );
});

describe('describing one member', function () {
    it('describes the member field by field from the account, the profile, the phone and the photo', function () {
        $data = $this->presenter->describe($this->owner);

        expect($data)->toBeInstanceOf(TeamMemberData::class)
            ->and($data->id)->toBe(StaffFixtures::MEMBER_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->photoUrl)->toBe(StaffFixtures::PHOTO_URL)
            ->and($data->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
            ->and($data->about)->toBe(StaffFixtures::ABOUT)
            ->and($data->level)->toBe(StaffRole::Owner)
            ->and($data->invitationPending)->toBeFalse()
            ->and($data->temporaryPasswordAvailable)->toBeFalse()
            ->and($data->createdAt)->toEqual(StaffFixtures::now());
    });

    it('identifies the member by the staff member uuid, never by the profile or the account', function () {
        $data = $this->presenter->describe($this->owner);

        expect($data->id)->toBe(StaffFixtures::MEMBER_ID)
            ->and($data->id)->not->toBe(StaffFixtures::PROFILE_ID)
            ->and($data->id)->not->toBe(StaffFixtures::ACCOUNT_ID);
    });

    it('marks a staff member still holding the temporary password as invited', function () {
        expect($this->presenter->describe($this->invitee)->invitationPending)->toBeTrue();
    });

    it('refuses a member whose account is gone', function () {
        $stranded = StaffFixtures::member(accountId: '01930000-0000-7000-8000-0000000000a9');

        expect(fn () => $this->presenter->describe($stranded))
            ->toThrow(StaffMemberNotFound::class, 'Staff member ['.StaffFixtures::MEMBER_ID.'] was not found.');
    });

    it('describes a member with no profile with no description, no phone and no photo', function () {
        $this->profiles = new FakeStaffProfileRepository;
        $presenter = new TeamMemberPresenter($this->accounts, $this->profiles, $this->phones, $this->photos, $this->temporaryPasswords);

        $data = $presenter->describe($this->owner);

        expect($data->jobTitle)->toBeNull()
            ->and($data->about)->toBeNull()
            ->and($data->phone)->toBeNull()
            ->and($data->photoUrl)->toBeNull()
            ->and($this->phones->batchReads)->toBe([[]]);
    });
});

describe('describing many members', function () {
    it('describes nothing, asking nobody, for no members', function () {
        expect($this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, []))->toBe([])
            ->and($this->accounts->calls)->toBe([])
            ->and($this->phones->batchReads)->toBe([])
            ->and($this->photos->reads)->toBe([]);
    });

    it('describes every member in the order it was handed them', function () {
        $described = $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, [$this->invitee, $this->owner]);

        expect(array_map(static fn (TeamMemberData $member): string => $member->id, $described))
            ->toBe([StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::MEMBER_ID])
            ->and($described[0]->name)->toBe('Grace Hopper')
            ->and($described[0]->phone)->toBeNull()
            ->and($described[0]->photoUrl)->toBeNull()
            ->and($described[0]->jobTitle)->toBeNull()
            ->and($described[0]->level)->toBe(StaffRole::Member)
            ->and($described[0]->createdAt)->toEqual(new DateTimeImmutable('2026-02-01T09:00:00+00:00'));
    });

    it('asks for the accounts, the phones and the photos once for the whole batch', function () {
        $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, [$this->owner, $this->invitee]);

        expect($this->accounts->calls)->toBe([[StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]])
            ->and($this->phones->batchReads)->toBe([[StaffFixtures::PROFILE_ID, StaffFixtures::SECOND_PROFILE_ID]]);
    });

    it('drops a member whose account is gone and keeps the rest', function () {
        $stranded = StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: '01930000-0000-7000-8000-0000000000a9');

        $described = $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, [$this->owner, $stranded]);

        expect($described)->toHaveCount(1)
            ->and($described[0]->id)->toBe(StaffFixtures::MEMBER_ID);
    });
});

describe('the temporary password an owner may copy', function () {
    it('offers it for an invited member whose account still holds one', function () {
        $this->temporaryPasswords->holds(StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD);

        expect($this->presenter->describe($this->invitee)->temporaryPasswordAvailable)->toBeTrue();
    });

    it('does not offer it for an invited member whose account holds none', function () {
        expect($this->presenter->describe($this->invitee)->temporaryPasswordAvailable)->toBeFalse();
    });

    it('does not offer it for a member with no pending invitation, even when the account still holds one', function () {
        $this->temporaryPasswords->holds(StaffFixtures::ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD);

        expect($this->presenter->describe($this->owner)->temporaryPasswordAvailable)->toBeFalse();
    });

    it('asks which accounts hold one once for the whole batch, by account uuid', function () {
        $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, [$this->owner, $this->invitee]);

        expect($this->temporaryPasswords->batchReads)->toBe([[StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]]);
    });

    it('asks about an account shared by two members only once', function () {
        $twin = StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member);

        $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, [$this->invitee, $twin]);

        expect($this->temporaryPasswords->batchReads)->toBe([[StaffFixtures::SECOND_ACCOUNT_ID]]);
    });

    it('never reveals a password while describing the team', function () {
        $this->temporaryPasswords->holds(StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD);

        $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, [$this->owner, $this->invitee]);

        expect($this->temporaryPasswords->reveals)->toBe([]);
    });

    it('asks nothing for no members', function () {
        $this->presenter->describeMany(FakeBusinessContext::BUSINESS_ID, []);

        expect($this->temporaryPasswords->batchReads)->toBe([]);
    });
});

describe('tenant isolation', function () {
    it('reads profiles and photos only from the business it was asked about', function () {
        $this->profiles->store(StaffFixtures::profile(
            id: '01930000-0000-7000-8000-0000000000e9',
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
            jobTitle: 'Somewhere else',
        ));
        $this->photos->store(StaffFixtures::OTHER_BUSINESS_ID, StaffFixtures::PROFILE_ID, 'https://cdn.mizita.test/staff/elsewhere.webp');

        $data = $this->presenter->describe($this->owner);

        expect($data->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
            ->and($data->photoUrl)->toBe(StaffFixtures::PHOTO_URL)
            ->and(array_unique(array_column($this->photos->reads, 'businessId')))->toBe([FakeBusinessContext::BUSINESS_ID]);
    });

    it('reads a member of another business from that business, so nothing filed here leaks onto it', function () {
        $elsewhere = StaffFixtures::member(businessId: StaffFixtures::OTHER_BUSINESS_ID);

        $data = $this->presenter->describe($elsewhere);

        expect($data->jobTitle)->toBeNull()
            ->and($data->photoUrl)->toBeNull()
            ->and($this->photos->reads)->toBe([]);
    });
});

it('describes a page keeping its total and its pagination', function () {
    $pagination = Pagination::of(2, 1);

    $page = $this->presenter->describePage(FakeBusinessContext::BUSINESS_ID, Paginated::of([$this->invitee], 2, $pagination));

    expect($page->items)->toHaveCount(1)
        ->and($page->items[0])->toBeInstanceOf(TeamMemberData::class)
        ->and($page->items[0]->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($page->total)->toBe(2)
        ->and($page->pagination)->toBe($pagination);
});
