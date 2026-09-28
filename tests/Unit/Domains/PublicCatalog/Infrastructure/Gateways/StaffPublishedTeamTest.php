<?php

declare(strict_types=1);

use App\Domains\PublicCatalog\Infrastructure\Gateways\StaffPublishedTeam;
use App\Domains\PublicCatalog\ValueObjects\PublicTeamMember;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\Services\BookingLinks;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\PublicCatalog\PublicCatalogFixtures;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\StaffFixtures;

beforeEach(function () {
    $this->members = Mockery::mock(StaffMemberRepository::class);
    $this->profiles = new FakeStaffProfileRepository;
    $this->photos = new FakeStaffProfilePhotos;
    $this->bookingLinks = new BookingLinks(PublicCatalogFixtures::BOOKING_BASE_URL);

    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(id: StaffFixtures::ACCOUNT_ID, name: 'Ada Lovelace'),
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper'),
    );

    $this->teamOf = fn (
        FakeAccountDirectory $accounts,
        ?StaffProfileRepository $profiles = null,
        ?StaffProfilePhotos $photos = null,
    ): array => (new StaffPublishedTeam(
        $this->members,
        $accounts,
        $profiles ?? $this->profiles,
        $photos ?? $this->photos,
        $this->bookingLinks,
    ))->forBusiness(PublicCatalogFixtures::BUSINESS_ID, PublicCatalogFixtures::SLUG);

    $this->read = fn (): array => ($this->teamOf)($this->accounts);

    $this->staffed = function (): void {
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
    };

    $this->profileOf = fn (
        string $profileId,
        string $staffMemberId,
        ?string $jobTitle = StaffFixtures::JOB_TITLE,
        ?string $about = StaffFixtures::ABOUT,
        ?string $bookingSlug = null,
        string $businessId = PublicCatalogFixtures::BUSINESS_ID,
    ) => StaffFixtures::profile(
        id: $profileId,
        staffMemberId: $staffMemberId,
        businessId: $businessId,
        jobTitle: $jobTitle,
        about: $about,
        bookingSlug: $bookingSlug,
    );
});

describe('the team a visitor sees', function () {
    it('publishes each member under the name their account carries', function () {
        ($this->staffed)();

        $team = ($this->read)();

        expect($team)->toHaveCount(2)
            ->and($team[0])->toBeInstanceOf(PublicTeamMember::class)
            ->and($team[0]->id)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($team[0]->name)->toBe('Ada Lovelace')
            ->and($team[1]->id)->toBe(PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID)
            ->and($team[1]->name)->toBe('Grace Hopper');
    });

    it('identifies a member by their staff uuid, never by the account or the profile behind it', function () {
        $this->members->shouldReceive('allForBusiness')->once()->andReturn([
            StaffFixtures::member(
                id: PublicCatalogFixtures::TEAM_MEMBER_ID,
                accountId: StaffFixtures::ACCOUNT_ID,
                businessId: PublicCatalogFixtures::BUSINESS_ID,
            ),
        ]);
        $this->profiles->store(($this->profileOf)(StaffFixtures::PROFILE_ID, PublicCatalogFixtures::TEAM_MEMBER_ID));

        $id = ($this->read)()[0]->id;

        expect($id)->toBe(PublicCatalogFixtures::TEAM_MEMBER_ID)
            ->and($id)->not->toBe(StaffFixtures::ACCOUNT_ID)
            ->and($id)->not->toBe(StaffFixtures::PROFILE_ID)
            ->and(is_numeric($id))->toBeFalse();
    });

    it('publishes the public header of a member and nothing else, so no email, phone or account reaches a visitor', function () {
        $fields = array_map(
            static fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(PublicTeamMember::class))->getProperties(),
        );

        expect($fields)->toBe(['id', 'name', 'photoUrl', 'jobTitle', 'about', 'bookingUrl'])
            ->and($fields)->not->toContain('email')
            ->and($fields)->not->toContain('phone')
            ->and($fields)->not->toContain('role')
            ->and($fields)->not->toContain('accountId')
            ->and($fields)->not->toContain('bookingSlug');
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

describe('a business with no staff', function () {
    it('answers with an empty team and asks no neighbour anything', function () {
        $profiles = Mockery::mock(StaffProfileRepository::class);
        $photos = Mockery::mock(StaffProfilePhotos::class);

        $this->members->shouldReceive('allForBusiness')->once()->andReturn([]);
        $profiles->shouldNotReceive('findForStaffMembers');
        $photos->shouldNotReceive('urlsFor');

        expect(($this->teamOf)($this->accounts, $profiles, $photos))->toBe([])
            ->and($this->accounts->callCount())->toBe(0);
    });
});

describe('the public header of each member', function () {
    it('carries the job title and about the member filed on their profile', function () {
        ($this->staffed)();
        $this->profiles->store(($this->profileOf)(StaffFixtures::PROFILE_ID, PublicCatalogFixtures::TEAM_MEMBER_ID));

        $member = ($this->read)()[0];

        expect($member->jobTitle)->toBe(StaffFixtures::JOB_TITLE)
            ->and($member->about)->toBe(StaffFixtures::ABOUT);
    });

    it('hands each member the photo filed under their own profile id', function () {
        ($this->staffed)();
        $this->profiles->store(
            ($this->profileOf)(StaffFixtures::PROFILE_ID, PublicCatalogFixtures::TEAM_MEMBER_ID),
            ($this->profileOf)(StaffFixtures::SECOND_PROFILE_ID, PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID),
        );
        $this->photos->store(PublicCatalogFixtures::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID, StaffFixtures::PHOTO_URL);

        $team = ($this->read)();

        expect($team[0]->photoUrl)->toBeNull()
            ->and($team[1]->photoUrl)->toBe(StaffFixtures::PHOTO_URL);
    });

    it('publishes no photo a neighbouring business filed under that same profile id', function () {
        ($this->staffed)();
        $this->profiles->store(($this->profileOf)(StaffFixtures::PROFILE_ID, PublicCatalogFixtures::TEAM_MEMBER_ID));
        $this->photos->store(PublicCatalogFixtures::OTHER_BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

        expect(array_column(($this->read)(), 'photoUrl'))->toBe([null, null]);
    });

    it('leaves every detail null for a member who has no profile yet', function () {
        ($this->staffed)();
        $this->photos->store(PublicCatalogFixtures::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

        $member = ($this->read)()[0];

        expect($member->photoUrl)->toBeNull()
            ->and($member->jobTitle)->toBeNull()
            ->and($member->about)->toBeNull()
            ->and($member->bookingUrl)->toBeNull();
    });

    it('never reads the profile another business keeps for that same staff member', function () {
        ($this->staffed)();
        $this->profiles->store(($this->profileOf)(
            StaffFixtures::PROFILE_ID,
            PublicCatalogFixtures::TEAM_MEMBER_ID,
            bookingSlug: PublicCatalogFixtures::STAFF_SLUG,
            businessId: PublicCatalogFixtures::OTHER_BUSINESS_ID,
        ));

        $member = ($this->read)()[0];

        expect($member->jobTitle)->toBeNull()
            ->and($member->about)->toBeNull()
            ->and($member->bookingUrl)->toBeNull();
    });

    it('leaves the job title and about null when the member filled neither in', function () {
        ($this->staffed)();
        $this->profiles->store(($this->profileOf)(
            StaffFixtures::PROFILE_ID,
            PublicCatalogFixtures::TEAM_MEMBER_ID,
            jobTitle: null,
            about: null,
        ));

        $member = ($this->read)()[0];

        expect($member->jobTitle)->toBeNull()
            ->and($member->about)->toBeNull();
    });
});

describe('the booking link of each member', function () {
    it('builds the link under the business slug and the member booking slug', function () {
        ($this->staffed)();
        $this->profiles->store(($this->profileOf)(
            StaffFixtures::PROFILE_ID,
            PublicCatalogFixtures::TEAM_MEMBER_ID,
            bookingSlug: PublicCatalogFixtures::STAFF_SLUG,
        ));

        expect(($this->read)()[0]->bookingUrl)->toBe(PublicCatalogFixtures::TEAM_BOOKING_URL)
            ->and(PublicCatalogFixtures::TEAM_BOOKING_URL)->toBe('https://mizita.test/ada-salon/equipo/ada-lovelace');
    });

    it('publishes no link for a member who was never given a booking slug', function () {
        ($this->staffed)();
        $this->profiles->store(
            ($this->profileOf)(
                StaffFixtures::PROFILE_ID,
                PublicCatalogFixtures::TEAM_MEMBER_ID,
                bookingSlug: PublicCatalogFixtures::STAFF_SLUG,
            ),
            ($this->profileOf)(StaffFixtures::SECOND_PROFILE_ID, PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID),
        );

        expect(array_column(($this->read)(), 'bookingUrl'))->toBe([PublicCatalogFixtures::TEAM_BOOKING_URL, null]);
    });

    it('hangs the link off whichever business slug the page was read under', function () {
        ($this->staffed)();
        $this->profiles->store(($this->profileOf)(
            StaffFixtures::PROFILE_ID,
            PublicCatalogFixtures::TEAM_MEMBER_ID,
            bookingSlug: 'jose-pablo-2',
        ));

        $team = (new StaffPublishedTeam($this->members, $this->accounts, $this->profiles, $this->photos, $this->bookingLinks))
            ->forBusiness(PublicCatalogFixtures::BUSINESS_ID, 'peluqueria-ambar');

        expect($team[0]->bookingUrl)->toBe('https://mizita.test/peluqueria-ambar/equipo/jose-pablo-2');
    });
});

describe('reading the team in a batch', function () {
    beforeEach(function () {
        $this->profilesPort = Mockery::mock(StaffProfileRepository::class);
        $this->photosPort = Mockery::mock(StaffProfilePhotos::class);

        ($this->staffed)();
    });

    it('loads every profile in one call, scoped to the business, rather than one call per member', function () {
        $this->profilesPort->shouldReceive('findForStaffMembers')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, [
                PublicCatalogFixtures::TEAM_MEMBER_ID,
                PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
            ])
            ->andReturn([]);
        $this->profilesPort->shouldNotReceive('findForStaffMember');
        $this->photosPort->shouldReceive('urlsFor')->once()->andReturn([]);

        expect(($this->teamOf)($this->accounts, $this->profilesPort, $this->photosPort))->toHaveCount(2);
    });

    it('asks for every photo in one call, by profile id, rather than one call per member', function () {
        $this->profilesPort->shouldReceive('findForStaffMembers')->once()->andReturn([
            PublicCatalogFixtures::TEAM_MEMBER_ID => ($this->profileOf)(
                StaffFixtures::PROFILE_ID,
                PublicCatalogFixtures::TEAM_MEMBER_ID,
            ),
            PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID => ($this->profileOf)(
                StaffFixtures::SECOND_PROFILE_ID,
                PublicCatalogFixtures::SECOND_TEAM_MEMBER_ID,
            ),
        ]);
        $this->photosPort->shouldReceive('urlsFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, [StaffFixtures::PROFILE_ID, StaffFixtures::SECOND_PROFILE_ID])
            ->andReturn([StaffFixtures::PROFILE_ID => StaffFixtures::PHOTO_URL]);
        $this->photosPort->shouldNotReceive('urlFor');

        expect(array_column(($this->teamOf)($this->accounts, $this->profilesPort, $this->photosPort), 'photoUrl'))
            ->toBe([StaffFixtures::PHOTO_URL, null]);
    });

    it('still asks for the photos once, with an empty list, when no member has a profile', function () {
        $this->profilesPort->shouldReceive('findForStaffMembers')->once()->andReturn([]);
        $this->photosPort->shouldReceive('urlsFor')->once()
            ->with(PublicCatalogFixtures::BUSINESS_ID, [])
            ->andReturn([]);

        expect(array_column(($this->teamOf)($this->accounts, $this->profilesPort, $this->photosPort), 'photoUrl'))
            ->toBe([null, null]);
    });
});
