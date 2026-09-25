<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\StaffMemberSummary;
use App\Domains\Staff\Application\UseCases\ListStaffMembers;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Contracts\StaffProfilePhotos;
use App\Domains\Staff\Contracts\StaffProfileRepository;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\FakeBusinessContext;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\StaffFixtures;

const SECOND_PHOTO_URL = 'https://cdn.mizita.test/staff/grace.webp';

beforeEach(function () {
    $this->members = Mockery::mock(StaffMemberRepository::class);
    $this->ada = StaffFixtures::account(name: 'Ada Lovelace', email: 'ada@example.com');
    $this->grace = StaffFixtures::account(
        id: StaffFixtures::SECOND_ACCOUNT_ID,
        name: 'Grace Hopper',
        email: 'grace@example.com',
    );
    $this->profiles = new FakeStaffProfileRepository;
    $this->photos = new FakeStaffProfilePhotos;

    $this->build = function (array $members, FakeAccountDirectory $accounts): ListStaffMembers {
        $this->members->shouldReceive('allForBusiness')
            ->with(FakeBusinessContext::BUSINESS_ID)
            ->andReturn($members)
            ->byDefault();

        $this->accounts = $accounts;

        return new ListStaffMembers(
            $this->members,
            $accounts,
            new FakeBusinessContext,
            $this->profiles,
            $this->photos,
        );
    };

    $this->adaAndGrace = fn (): array => [
        StaffFixtures::member(role: StaffRole::Owner),
        StaffFixtures::member(
            id: StaffFixtures::SECOND_MEMBER_ID,
            accountId: StaffFixtures::SECOND_ACCOUNT_ID,
            role: StaffRole::Member,
        ),
    ];
});

it('answers with a summary of every staff member of the business', function () {
    $this->profiles->store(
        StaffFixtures::profile(),
        StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID),
    );
    $this->photos->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

    $useCase = ($this->build)(($this->adaAndGrace)(), new FakeAccountDirectory($this->ada, $this->grace));

    $summaries = $useCase->handle()->value();

    expect($summaries)->toHaveCount(2)
        ->and($summaries[0])->toBeInstanceOf(StaffMemberSummary::class)
        ->and($summaries[0]->id)->toBe(StaffFixtures::MEMBER_ID)
        ->and($summaries[0]->name)->toBe('Ada Lovelace')
        ->and($summaries[0]->email)->toBe('ada@example.com')
        ->and($summaries[0]->role)->toBe(StaffRole::Owner)
        ->and($summaries[0]->photoUrl)->toBe(StaffFixtures::PHOTO_URL)
        ->and($summaries[1]->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
        ->and($summaries[1]->name)->toBe('Grace Hopper')
        ->and($summaries[1]->email)->toBe('grace@example.com')
        ->and($summaries[1]->role)->toBe(StaffRole::Member)
        ->and($summaries[1]->photoUrl)->toBeNull();
});

it('reads the staff of the business in context and of no other', function () {
    $this->members->shouldReceive('allForBusiness')->once()
        ->with(FakeBusinessContext::BUSINESS_ID)
        ->andReturn([]);

    $useCase = ($this->build)([], new FakeAccountDirectory);

    expect($useCase->handle()->succeeded())->toBeTrue();
});

it('keeps the order the repository listed the members in', function () {
    $useCase = ($this->build)(
        [
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
            StaffFixtures::member(),
        ],
        new FakeAccountDirectory($this->ada, $this->grace),
    );

    expect(array_map(
        static fn (StaffMemberSummary $summary): string => $summary->id,
        $useCase->handle()->value(),
    ))->toBe([StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::MEMBER_ID]);
});

it('asks the accounts once, with each account named a single time', function () {
    $useCase = ($this->build)(
        [
            StaffFixtures::member(),
            StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID),
            StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID),
        ],
        new FakeAccountDirectory($this->ada, $this->grace),
    );

    $useCase->handle();

    expect($this->accounts->callCount())->toBe(1)
        ->and($this->accounts->lastCall())->toBe([StaffFixtures::ACCOUNT_ID, StaffFixtures::SECOND_ACCOUNT_ID]);
});

it('names two memberships of one account by that same account', function () {
    $useCase = ($this->build)(
        [StaffFixtures::member(), StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID)],
        new FakeAccountDirectory($this->ada),
    );

    expect(array_map(
        static fn (StaffMemberSummary $summary): string => $summary->name,
        $useCase->handle()->value(),
    ))->toBe(['Ada Lovelace', 'Ada Lovelace']);
});

it('drops a member whose account the directory does not hand back, and keeps the rest', function () {
    $this->profiles->store(
        StaffFixtures::profile(),
        StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID),
    );
    $this->photos
        ->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL)
        ->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID, SECOND_PHOTO_URL);

    $useCase = ($this->build)(($this->adaAndGrace)(), new FakeAccountDirectory($this->ada));

    $summaries = $useCase->handle()->value();

    expect($summaries)->toHaveCount(1)
        ->and($summaries[0]->id)->toBe(StaffFixtures::MEMBER_ID)
        ->and($summaries[0]->photoUrl)->toBe(StaffFixtures::PHOTO_URL);
});

describe('photo url', function () {
    it('hands each member the url of their own profile photo', function () {
        $this->profiles->store(
            StaffFixtures::profile(),
            StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID),
        );
        $this->photos
            ->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL)
            ->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::SECOND_PROFILE_ID, SECOND_PHOTO_URL);

        $useCase = ($this->build)(($this->adaAndGrace)(), new FakeAccountDirectory($this->ada, $this->grace));

        expect(array_map(
            static fn (StaffMemberSummary $summary): ?string => $summary->photoUrl,
            $useCase->handle()->value(),
        ))->toBe([StaffFixtures::PHOTO_URL, SECOND_PHOTO_URL]);
    });

    it('leaves the url empty for a member whose profile has no photo', function () {
        $this->profiles->store(StaffFixtures::profile());

        $useCase = ($this->build)([StaffFixtures::member()], new FakeAccountDirectory($this->ada));

        expect($useCase->handle()->value()[0]->photoUrl)->toBeNull();
    });

    it('leaves the url empty for a member with no profile at all', function () {
        $this->photos->store(FakeBusinessContext::BUSINESS_ID, StaffFixtures::PROFILE_ID, StaffFixtures::PHOTO_URL);

        $useCase = ($this->build)([StaffFixtures::member()], new FakeAccountDirectory($this->ada));

        expect($useCase->handle()->value()[0]->photoUrl)->toBeNull();
    });
});

describe('collaborator calls', function () {
    beforeEach(function () {
        $this->profiles = Mockery::mock(StaffProfileRepository::class);
        $this->photos = Mockery::mock(StaffProfilePhotos::class);
    });

    it('loads the profiles of every member in one call, scoped to the business in context', function () {
        $this->profiles->shouldReceive('findForStaffMembers')->once()
            ->with(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::MEMBER_ID, StaffFixtures::SECOND_MEMBER_ID])
            ->andReturn([]);
        $this->photos->shouldReceive('urlsFor')->andReturn([]);

        ($this->build)(($this->adaAndGrace)(), new FakeAccountDirectory($this->ada, $this->grace))->handle();
    });

    it('resolves the photos of every found profile in one call, scoped to the business in context', function () {
        $this->profiles->shouldReceive('findForStaffMembers')->andReturn([
            StaffFixtures::MEMBER_ID => StaffFixtures::profile(),
            StaffFixtures::SECOND_MEMBER_ID => StaffFixtures::profile(
                id: StaffFixtures::SECOND_PROFILE_ID,
                staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
            ),
        ]);
        $this->photos->shouldReceive('urlsFor')->once()
            ->with(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::PROFILE_ID, StaffFixtures::SECOND_PROFILE_ID])
            ->andReturn([]);

        ($this->build)(($this->adaAndGrace)(), new FakeAccountDirectory($this->ada, $this->grace))->handle();
    });

    it('asks for photos only of the profiles that exist', function () {
        $this->profiles->shouldReceive('findForStaffMembers')->andReturn([
            StaffFixtures::SECOND_MEMBER_ID => StaffFixtures::profile(
                id: StaffFixtures::SECOND_PROFILE_ID,
                staffMemberId: StaffFixtures::SECOND_MEMBER_ID,
            ),
        ]);
        $this->photos->shouldReceive('urlsFor')->once()
            ->with(FakeBusinessContext::BUSINESS_ID, [StaffFixtures::SECOND_PROFILE_ID])
            ->andReturn([]);

        ($this->build)(($this->adaAndGrace)(), new FakeAccountDirectory($this->ada, $this->grace))->handle();
    });

    it('answers an empty business with an empty list and asks no collaborator anything', function () {
        $this->profiles->shouldNotReceive('findForStaffMembers');
        $this->photos->shouldNotReceive('urlsFor');

        $useCase = ($this->build)([], new FakeAccountDirectory);

        $response = $useCase->handle();

        expect($response->succeeded())->toBeTrue()
            ->and($response->value())->toBe([])
            ->and($this->accounts->callCount())->toBe(0);
    });
});
