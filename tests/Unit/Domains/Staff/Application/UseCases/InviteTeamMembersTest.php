<?php

declare(strict_types=1);

use App\Domains\Staff\Application\Dtos\InviteTeamMembersInput;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Application\UseCases\InviteTeamMembers;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Exceptions\TeamMemberAlreadyExists;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeStaffPhoneBook;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\FakeTeamAccountProvisioner;
use Tests\Support\Staff\FakeTeamRoster;
use Tests\Support\Staff\FakeTeamTemporaryPasswords;
use Tests\Support\Staff\StaffFixtures;
use Tests\Support\Staff\StaffJournal;

/**
 * @return array{members: list<array{name: string, email: string, level: string}>}
 */
function teamInvitationPayload(): array
{
    return ['members' => [
        ['name' => '  Grace Hopper ', 'email' => ' Grace@Example.com ', 'level' => 'staff'],
        ['name' => 'Linus Pauling', 'email' => 'linus@example.com', 'level' => 'no_access'],
    ]];
}

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->journal = new StaffJournal($this->transactions);
    $this->members = new FakeStaffMemberRepository($this->journal);
    $this->profiles = new FakeStaffProfileRepository($this->journal);
    $this->roster = new FakeTeamRoster($this->journal);
    $this->provisioner = (new FakeTeamAccountProvisioner($this->journal))
        ->provides('grace@example.com', StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD)
        ->provides('linus@example.com', StaffFixtures::THIRD_ACCOUNT_ID, null);
    $this->accounts = new FakeAccountDirectory(
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com', awaitingPasswordChange: true),
        StaffFixtures::account(id: StaffFixtures::THIRD_ACCOUNT_ID, name: 'Linus Pauling', email: 'linus@example.com', hasPassword: false),
    );
    $this->clock = new FakeClock(new DateTimeImmutable('2026-03-29T01:30:00+00:00'));

    $this->dispatched = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): void {
        $this->journal->record('events.dispatch');
        $this->dispatched[] = $event;
    });

    $this->build = fn (?FakeBusinessContext $business = null): InviteTeamMembers => new InviteTeamMembers(
        $this->members,
        $this->profiles,
        $this->roster,
        $this->provisioner,
        new TeamMemberPresenter($this->accounts, $this->profiles, new FakeStaffPhoneBook, new FakeStaffProfilePhotos, new FakeTeamTemporaryPasswords),
        $business ?? new FakeBusinessContext,
        new FixedIdGenerator(
            StaffFixtures::SECOND_MEMBER_ID,
            StaffFixtures::SECOND_PROFILE_ID,
            StaffFixtures::THIRD_MEMBER_ID,
            StaffFixtures::THIRD_PROFILE_ID,
        ),
        $this->clock,
        $this->transactions,
        $this->events,
    );

    $this->invite = fn (array $payload = [], ?InviteTeamMembers $useCase = null): UseCaseResponse => ($useCase ?? ($this->build)())
        ->handle(InviteTeamMembersInput::fromRequest($payload === [] ? teamInvitationPayload() : $payload));

    $this->refusal = function (array $payload = []): UseCaseError {
        $response = ($this->invite)($payload);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->nothingWasCommittedOrAnnounced = function (): void {
        expect($this->members->saved)->toBe([])
            ->and($this->profiles->saved)->toBe([])
            ->and($this->provisioner->provisions)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->dispatched)->toBe([]);
    };
});

describe('inviting a batch', function () {
    it('answers with every invited member, field by field, in batch order', function () {
        $data = ($this->invite)()->value();

        expect($data)->toHaveCount(2)
            ->and($data[0])->toBeInstanceOf(TeamMemberData::class)
            ->and($data[0]->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($data[0]->name)->toBe('Grace Hopper')
            ->and($data[0]->email)->toBe('grace@example.com')
            ->and($data[0]->phone)->toBeNull()
            ->and($data[0]->photoUrl)->toBeNull()
            ->and($data[0]->jobTitle)->toBeNull()
            ->and($data[0]->about)->toBeNull()
            ->and($data[0]->level)->toBe(StaffRole::Member)
            ->and($data[0]->invitationPending)->toBeTrue()
            ->and($data[0]->createdAt)->toEqual(new DateTimeImmutable('2026-03-29T01:30:00+00:00'))
            ->and($data[1]->id)->toBe(StaffFixtures::THIRD_MEMBER_ID)
            ->and($data[1]->name)->toBe('Linus Pauling')
            ->and($data[1]->level)->toBe(StaffRole::NoAccess)
            ->and($data[1]->invitationPending)->toBeFalse();
    });

    it('provisions each account at its level, with the name trimmed and the email normalized', function () {
        ($this->invite)();

        expect($this->provisioner->provisions)->toBe([
            ['level' => StaffRole::Member, 'name' => 'Grace Hopper', 'email' => 'grace@example.com'],
            ['level' => StaffRole::NoAccess, 'name' => 'Linus Pauling', 'email' => 'linus@example.com'],
        ]);
    });

    it('registers each member at its level, for the provisioned account, at the clock instant', function () {
        ($this->invite)();

        [$grace, $linus] = $this->members->saved;

        expect($this->members->saved)->toHaveCount(2)
            ->and($grace->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($grace->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($grace->role())->toBe(StaffRole::Member)
            ->and($grace->createdAt)->toEqual($this->clock->now())
            ->and($linus->id)->toBe(StaffFixtures::THIRD_MEMBER_ID)
            ->and($linus->accountId)->toBe(StaffFixtures::THIRD_ACCOUNT_ID)
            ->and($linus->role())->toBe(StaffRole::NoAccess);
    });

    it('gives every member a blank profile of their own', function () {
        ($this->invite)();

        [$grace, $linus] = $this->profiles->saved;

        expect($this->profiles->saved)->toHaveCount(2)
            ->and($grace->id)->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($grace->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($grace->jobTitle())->toBeNull()
            ->and($grace->about())->toBeNull()
            ->and($grace->createdAt)->toEqual($this->clock->now())
            ->and($linus->id)->toBe(StaffFixtures::THIRD_PROFILE_ID)
            ->and($linus->staffMemberId)->toBe(StaffFixtures::THIRD_MEMBER_ID);
    });

    it('checks the team before writing, then writes the whole batch inside one transaction', function () {
        ($this->invite)();

        expect($this->journal->entries)->toBe([
            'roster.emails',
            'accounts.provision', 'members.save', 'profiles.save',
            'accounts.provision', 'members.save', 'profiles.save',
            'events.dispatch',
        ])
            ->and($this->journal->outsideTransaction)->toBe(['roster.emails', 'events.dispatch'])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('checks the team for the normalized emails of the batch', function () {
        ($this->invite)();

        expect($this->roster->emailChecks)->toBe([[
            'businessId' => FakeBusinessContext::BUSINESS_ID,
            'emails' => ['grace@example.com', 'linus@example.com'],
        ]]);
    });
});

describe('announcing the invitations', function () {
    it('announces one invitation per member given access, after the commit, with the temporary password', function () {
        ($this->invite)();

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(TeamMemberInvited::class)
            ->and($this->dispatched[0]->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($this->dispatched[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->dispatched[0]->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($this->dispatched[0]->temporaryPassword)->toBe(StaffFixtures::TEMPORARY_PASSWORD)
            ->and($this->journal->outsideTransaction)->toContain('events.dispatch');
    });

    it('announces nothing for a batch where nobody is given access', function () {
        ($this->invite)(['members' => [['name' => 'Linus Pauling', 'email' => 'linus@example.com', 'level' => 'no_access']]]);

        expect($this->dispatched)->toBe([])
            ->and($this->members->saved)->toHaveCount(1);
    });

    it('announces an account that already existed with no password to send', function () {
        $this->provisioner->provides('linus@example.com', StaffFixtures::THIRD_ACCOUNT_ID, null);

        ($this->invite)(['members' => [['name' => 'Linus Pauling', 'email' => 'linus@example.com', 'level' => 'staff']]]);

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0]->accountId)->toBe(StaffFixtures::THIRD_ACCOUNT_ID)
            ->and($this->dispatched[0]->temporaryPassword)->toBeNull();
    });

    it('announces every member given access, in batch order', function () {
        ($this->invite)(['members' => [
            ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'level' => 'staff'],
            ['name' => 'Linus Pauling', 'email' => 'linus@example.com', 'level' => 'staff'],
        ]]);

        expect(array_map(static fn (TeamMemberInvited $event): string => $event->staffMemberId, $this->dispatched))
            ->toBe([StaffFixtures::SECOND_MEMBER_ID, StaffFixtures::THIRD_MEMBER_ID]);
    });

    it('lets a listener failure after the commit escape, rather than refusing a batch that was written', function () {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('the mail queue is down'));
        $this->events = $events;

        expect(fn () => ($this->invite)())->toThrow(RuntimeException::class, 'the mail queue is down')
            ->and($this->members->saved)->toHaveCount(2);
    });

    it('describes nobody when a listener fails after the commit', function () {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldReceive('dispatch')->andThrow(new RuntimeException('the mail queue is down'));
        $this->events = $events;

        expect(fn () => ($this->invite)())->toThrow(RuntimeException::class, 'the mail queue is down')
            ->and($this->accounts->callCount())->toBe(0);
    });

    it('announces the invitations before describing the batch', function () {
        $directory = $this->accounts;
        $journal = $this->journal;
        $this->accounts = Mockery::mock(AccountDirectory::class);
        $this->accounts->shouldReceive('describe')->andReturnUsing(function (array $accountIds) use ($directory, $journal): array {
            $journal->record('presenter.describe');

            return $directory->describe($accountIds);
        });

        ($this->invite)()->value();

        expect(array_slice($this->journal->entries, -2))->toBe(['events.dispatch', 'presenter.describe'])
            ->and($this->journal->outsideTransaction)->toBe(['roster.emails', 'events.dispatch', 'presenter.describe']);
    });

    it('lets a presenter refusal escape with the invitations already announced, rather than refusing a batch that was written', function () {
        $this->accounts = Mockery::mock(AccountDirectory::class);
        $this->accounts->shouldReceive('describe')->andThrow(StaffMemberNotFound::forAccount(StaffFixtures::SECOND_ACCOUNT_ID));

        expect(fn () => ($this->invite)())->toThrow(StaffMemberNotFound::class)
            ->and($this->transactions->runs())->toBe(1)
            ->and($this->members->saved)->toHaveCount(2)
            ->and($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0]->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($this->dispatched[0]->temporaryPassword)->toBe(StaffFixtures::TEMPORARY_PASSWORD);
    });
});

describe('tenant isolation', function () {
    it('puts every member, profile and invitation in the business of the context', function () {
        ($this->invite)(useCase: ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)));

        expect(array_unique(array_map(static fn ($member): string => $member->businessId, $this->members->saved)))
            ->toBe([StaffFixtures::OTHER_BUSINESS_ID])
            ->and(array_unique(array_map(static fn ($profile): string => $profile->businessId, $this->profiles->saved)))
            ->toBe([StaffFixtures::OTHER_BUSINESS_ID])
            ->and($this->roster->emailChecks[0]['businessId'])->toBe(StaffFixtures::OTHER_BUSINESS_ID)
            ->and($this->dispatched[0]->businessId)->toBe(StaffFixtures::OTHER_BUSINESS_ID);
    });

    it('lets somebody on another team be invited here', function () {
        $this->roster->withEmailsOnTeam(StaffFixtures::OTHER_BUSINESS_ID, 'grace@example.com');

        expect(($this->invite)()->succeeded())->toBeTrue()
            ->and($this->members->saved)->toHaveCount(2);
    });
});

describe('refusals decided before anything is written', function () {
    it('refuses the payload with the failure its first broken rule names', function (array $payload, string $code) {
        $error = ($this->refusal)($payload);

        expect($error->code)->toBe($code)
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->roster->emailChecks)->toBe([]);

        ($this->nothingWasCommittedOrAnnounced)();
    })->with([
        'nobody' => [['members' => []], 'invalid_team_invitation'],
        'no members key at all' => [['something' => 'else'], 'invalid_team_invitation'],
        'one past the limit' => [['members' => array_map(
            static fn (int $index): array => ['name' => 'Member', 'email' => "member{$index}@example.com", 'level' => 'staff'],
            range(1, 21),
        )], 'invalid_team_invitation'],
        'a blank name' => [['members' => [['name' => ' ', 'email' => 'grace@example.com', 'level' => 'staff']]], 'invalid_profile_name'],
        'a malformed email' => [['members' => [['name' => 'Grace', 'email' => 'grace', 'level' => 'staff']]], 'invalid_team_member_email'],
        'the owner level' => [['members' => [['name' => 'Grace', 'email' => 'grace@example.com', 'level' => 'owner']]], 'invalid_team_level'],
        'the same email twice' => [['members' => [
            ['name' => 'Grace', 'email' => 'grace@example.com', 'level' => 'staff'],
            ['name' => 'Grace again', 'email' => 'GRACE@example.com', 'level' => 'no_access'],
        ]], 'duplicate_team_invitation_email'],
    ]);

    it('refuses the whole batch when anyone in it is already on the team', function () {
        $this->roster->withEmailsOnTeam(FakeBusinessContext::BUSINESS_ID, 'linus@example.com');

        $error = ($this->refusal)();

        expect($error->code)->toBe('team_member_already_exists')
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($error->cause()?->getMessage())->toBe('[linus@example.com] is already a member of this business.');

        ($this->nothingWasCommittedOrAnnounced)();
    });
});

describe('refusals raised inside the transaction', function () {
    it('announces nobody when a later row clashes with the team, so the batch is all or nothing', function () {
        $clash = TeamMemberAlreadyExists::forAccount(StaffFixtures::THIRD_ACCOUNT_ID, new RuntimeException('unique violation'));
        $this->members->refuseSaveWith($clash, afterSaves: 1);

        $error = ($this->refusal)(['members' => [
            ['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'level' => 'staff'],
            ['name' => 'Linus Pauling', 'email' => 'linus@example.com', 'level' => 'staff'],
        ]]);

        expect($error->code)->toBe('team_member_already_exists')
            ->and($error->cause())->toBe($clash)
            ->and($this->dispatched)->toBe([])
            ->and($this->journal->outsideTransaction)->toBe(['roster.emails'])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('announces nobody when the account side refuses a later row', function () {
        $this->provisioner->refuseProvisioningWith(
            'linus@example.com',
            InvalidTeamMemberEmail::rejectedByAccount(new RuntimeException('the account refused it')),
        );

        $error = ($this->refusal)();

        expect($error->code)->toBe('invalid_team_member_email')
            ->and($error->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->profiles->saved)->toHaveCount(1)
            ->and($this->dispatched)->toBe([]);
    });

    it('announces nobody when the commit itself fails', function () {
        $this->transactions->failAtCommit(new RuntimeException('serialization failure'));

        expect(fn () => ($this->invite)())->toThrow(RuntimeException::class, 'serialization failure')
            ->and($this->dispatched)->toBe([]);
    });
});
