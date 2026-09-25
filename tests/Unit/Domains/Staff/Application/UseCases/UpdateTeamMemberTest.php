<?php

declare(strict_types=1);

use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Staff\Application\Dtos\TeamMemberData;
use App\Domains\Staff\Application\Dtos\UpdateTeamMemberInput;
use App\Domains\Staff\Application\Presenters\TeamMemberPresenter;
use App\Domains\Staff\Application\UseCases\UpdateTeamMember;
use App\Domains\Staff\Contracts\AccountDirectory;
use App\Domains\Staff\Events\TeamMemberInvited;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Application\UseCaseError;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\FakeBusinessContext;
use Tests\Support\FakePhoneNumberParser;
use Tests\Support\FakeTransactionManager;
use Tests\Support\PhoneNumbers;
use Tests\Support\Staff\FakeAccountDirectory;
use Tests\Support\Staff\FakeStaffMemberRepository;
use Tests\Support\Staff\FakeStaffPhoneBook;
use Tests\Support\Staff\FakeStaffProfilePhotos;
use Tests\Support\Staff\FakeStaffProfileRepository;
use Tests\Support\Staff\FakeTeamAccountProvisioner;
use Tests\Support\Staff\StaffFixtures;
use Tests\Support\Staff\StaffJournal;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function teamMemberUpdate(array $overrides = []): array
{
    return [
        'name' => '  Grace Brewster ',
        'job_title' => '  Colorista ',
        'about' => "\nEspecialista en rubios.\n",
        'phone' => ['country_code' => 'MX', 'national_number' => PhoneNumbers::MX_NATIONAL_NUMBER],
        'level' => 'no_access',
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->transactions = new FakeTransactionManager;
    $this->journal = new StaffJournal($this->transactions);
    $this->members = (new FakeStaffMemberRepository($this->journal))->store(
        StaffFixtures::member(),
        StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::Member),
    );
    $this->profiles = (new FakeStaffProfileRepository($this->journal))->store(
        StaffFixtures::profile(),
        StaffFixtures::profile(id: StaffFixtures::SECOND_PROFILE_ID, staffMemberId: StaffFixtures::SECOND_MEMBER_ID),
    );
    $this->accounts = (new FakeAccountDirectory(
        StaffFixtures::account(),
        StaffFixtures::account(id: StaffFixtures::SECOND_ACCOUNT_ID, name: 'Grace Hopper', email: 'grace@example.com'),
    ))->recordingInto($this->journal);
    $this->presenterAccounts = $this->accounts;
    $this->provisioner = (new FakeTeamAccountProvisioner($this->journal))
        ->issues(StaffFixtures::SECOND_ACCOUNT_ID, StaffFixtures::TEMPORARY_PASSWORD);
    $this->phones = new FakeStaffPhoneBook($this->journal);
    $this->photos = new FakeStaffProfilePhotos($this->journal);
    $this->parser = FakePhoneNumberParser::accepting(PhoneNumbers::mexican(), PhoneNumbers::american());

    $this->dispatched = [];
    $this->events = Mockery::mock(Dispatcher::class);
    $this->events->shouldReceive('dispatch')->andReturnUsing(function (object $event): void {
        $this->journal->record('events.dispatch');
        $this->dispatched[] = $event;
    });

    $this->build = fn (?FakeBusinessContext $business = null): UpdateTeamMember => new UpdateTeamMember(
        $this->members,
        $this->profiles,
        $this->accounts,
        $this->provisioner,
        $this->phones,
        new TeamMemberPresenter($this->presenterAccounts, $this->profiles, $this->phones, $this->photos),
        $this->parser,
        $business ?? new FakeBusinessContext,
        $this->transactions,
        $this->events,
    );

    $this->update = fn (
        array $payload,
        string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID,
        ?UpdateTeamMember $useCase = null,
    ): UseCaseResponse => ($useCase ?? ($this->build)())->handle(UpdateTeamMemberInput::fromRequest($payload, $staffMemberId));

    $this->refusal = function (array $payload, string $staffMemberId = StaffFixtures::SECOND_MEMBER_ID): UseCaseError {
        $response = ($this->update)($payload, $staffMemberId);

        expect($response->failed())->toBeTrue();

        return $response->error();
    };

    $this->nothingWasWritten = function (): void {
        expect($this->journal->entries)->toBe([])
            ->and($this->transactions->runs())->toBe(0)
            ->and($this->accounts->renames)->toBe([])
            ->and($this->profiles->saved)->toBe([])
            ->and($this->phones->replacements)->toBe([])
            ->and($this->members->saved)->toBe([])
            ->and($this->provisioner->issued)->toBe([])
            ->and($this->dispatched)->toBe([])
            ->and($this->members->stored(StaffFixtures::SECOND_MEMBER_ID)?->role())->toBe(StaffRole::Member)
            ->and($this->profiles->stored(StaffFixtures::SECOND_PROFILE_ID)?->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE);
    };
});

describe('updating a team member', function () {
    it('answers with the updated member, field by field', function () {
        $data = ($this->update)(teamMemberUpdate())->value();

        expect($data)->toBeInstanceOf(TeamMemberData::class)
            ->and($data->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($data->name)->toBe('Grace Brewster')
            ->and($data->email)->toBe('grace@example.com')
            ->and($data->phone?->e164())->toBe(PhoneNumbers::MX_E164)
            ->and($data->photoUrl)->toBeNull()
            ->and($data->jobTitle)->toBe('Colorista')
            ->and($data->about)->toBe('Especialista en rubios.')
            ->and($data->level)->toBe(StaffRole::NoAccess)
            ->and($data->invitationPending)->toBeFalse()
            ->and($data->createdAt)->toEqual(StaffFixtures::now());
    });

    it('renames the account behind the member with the name as sent', function () {
        ($this->update)(teamMemberUpdate());

        expect($this->accounts->renames)->toBe([['accountId' => StaffFixtures::SECOND_ACCOUNT_ID, 'name' => '  Grace Brewster ']]);
    });

    it('saves the profile with the job title and the description trimmed', function () {
        ($this->update)(teamMemberUpdate());

        expect($this->profiles->saved)->toHaveCount(1)
            ->and($this->profiles->saved[0]->id)->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($this->profiles->saved[0]->jobTitle()?->value)->toBe('Colorista')
            ->and($this->profiles->saved[0]->about()?->value)->toBe('Especialista en rubios.');
    });

    it('replaces the phone of the profile with the parsed number', function () {
        ($this->update)(teamMemberUpdate());

        expect($this->phones->replacements)->toHaveCount(1)
            ->and($this->phones->replacements[0]['profileId'])->toBe(StaffFixtures::SECOND_PROFILE_ID)
            ->and($this->phones->replacements[0]['phone']?->e164())->toBe(PhoneNumbers::MX_E164);
    });

    it('saves the member at the new level', function () {
        ($this->update)(teamMemberUpdate());

        expect($this->members->saved)->toHaveCount(1)
            ->and($this->members->saved[0]->id)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($this->members->saved[0]->role())->toBe(StaffRole::NoAccess);
    });

    it('writes the name, the profile, the phone and the level in that order, inside one transaction', function () {
        ($this->update)(teamMemberUpdate());

        expect($this->journal->entries)->toBe(['accounts.rename', 'profiles.save', 'phones.replace', 'members.save'])
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->transactions->runs())->toBe(1);
    });
});

describe('a partial update', function () {
    it('touches only what was sent, keeping every field left out', function () {
        $data = ($this->update)(['job_title' => 'Colorista'])->value();

        expect($this->journal->entries)->toBe(['profiles.save'])
            ->and($this->profiles->saved[0]->jobTitle()?->value)->toBe('Colorista')
            ->and($this->profiles->saved[0]->about()?->value)->toBe(StaffFixtures::ABOUT)
            ->and($data->name)->toBe('Grace Hopper')
            ->and($data->level)->toBe(StaffRole::Member);
    });

    it('clears a text field sent as null or blank, keeping the other', function (string $key, mixed $value) {
        ($this->update)([$key => $value]);

        $saved = $this->profiles->saved[0];

        expect($key === 'job_title' ? $saved->jobTitle() : $saved->about())->toBeNull()
            ->and($key === 'job_title' ? $saved->about()?->value : $saved->jobTitle()?->value)
            ->toBe($key === 'job_title' ? StaffFixtures::ABOUT : StaffFixtures::JOB_TITLE);
    })->with([
        'a null job title' => ['job_title', null],
        'a blank job title' => ['job_title', '   '],
        'a null description' => ['about', null],
        'a blank description' => ['about', "\n"],
    ]);

    it('removes the phone when it is sent empty, without consulting the parser', function (mixed $phone) {
        $this->phones->store(StaffFixtures::SECOND_PROFILE_ID, PhoneNumbers::american());

        $data = ($this->update)(['phone' => $phone])->value();

        expect($this->phones->replacements)->toBe([['profileId' => StaffFixtures::SECOND_PROFILE_ID, 'phone' => null]])
            ->and($data->phone)->toBeNull()
            ->and($this->parser->wasConsulted())->toBeFalse();
    })->with([
        'null' => [null],
        'an empty section' => [[]],
    ]);

    it('leaves the phone alone when it is left out', function () {
        $this->phones->store(StaffFixtures::SECOND_PROFILE_ID, PhoneNumbers::american());

        $data = ($this->update)(['name' => 'Grace Brewster'])->value();

        expect($this->phones->replacements)->toBe([])
            ->and($data->phone?->e164())->toBe(PhoneNumbers::US_E164);
    });

    it('saves nothing about the member when no level was sent', function () {
        ($this->update)(['name' => 'Grace Brewster']);

        expect($this->members->saved)->toBe([]);
    });

    it('lets the owner edit their own profile as long as the level is left out', function () {
        $data = ($this->update)(['name' => 'Ada King', 'job_title' => 'Dueña'], StaffFixtures::MEMBER_ID)->value();

        expect($data->name)->toBe('Ada King')
            ->and($data->jobTitle)->toBe('Dueña')
            ->and($data->level)->toBe(StaffRole::Owner)
            ->and($this->members->saved)->toBe([]);
    });
});

describe('changing the level', function () {
    it('invites a no access member given access, after the commit, with a fresh temporary password', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));

        $data = ($this->update)(['level' => 'staff'])->value();

        expect($data->level)->toBe(StaffRole::Member)
            ->and($this->provisioner->issued)->toBe([StaffFixtures::SECOND_ACCOUNT_ID])
            ->and($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(TeamMemberInvited::class)
            ->and($this->dispatched[0]->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($this->dispatched[0]->businessId)->toBe(FakeBusinessContext::BUSINESS_ID)
            ->and($this->dispatched[0]->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($this->dispatched[0]->temporaryPassword)->toBe(StaffFixtures::TEMPORARY_PASSWORD)
            ->and($this->journal->entries)->toBe(['profiles.save', 'members.save', 'accounts.issue_password', 'events.dispatch'])
            ->and($this->journal->outsideTransaction)->toBe(['events.dispatch']);
    });

    it('announces a member given access whose account keeps its own password with no password to send', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));
        $this->provisioner->issues(StaffFixtures::SECOND_ACCOUNT_ID, null);

        ($this->update)(['level' => 'staff']);

        expect($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0]->temporaryPassword)->toBeNull();
    });

    it('invites nobody when access is taken away or left as it was', function (StaffRole $from, string $to) {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: $from));

        $data = ($this->update)(['level' => $to])->value();

        expect($data->level->value)->toBe($to)
            ->and($this->members->saved)->toHaveCount(1)
            ->and($this->provisioner->issued)->toBe([])
            ->and($this->dispatched)->toBe([]);
    })->with([
        'staff to no access' => [StaffRole::Member, 'no_access'],
        'staff to staff' => [StaffRole::Member, 'staff'],
        'no access to no access' => [StaffRole::NoAccess, 'no_access'],
    ]);

    it('refuses to change the level of the owner, writing nothing', function (string $level) {
        $error = ($this->refusal)(teamMemberUpdate(['level' => $level]), StaffFixtures::MEMBER_ID);

        expect($error->code)->toBe('owner_level_is_fixed')
            ->and($error->kind)->toBe(DomainFailureKind::Conflict);

        ($this->nothingWasWritten)();
    })->with(['staff', 'no_access']);
});

describe('refusals decided before anything is written', function () {
    it('refuses the payload with the failure its first broken rule names', function (array $payload, string $staffMemberId, string $code, DomainFailureKind $kind) {
        $error = ($this->refusal)($payload, $staffMemberId);

        expect($error->code)->toBe($code)
            ->and($error->kind)->toBe($kind)
            ->and($this->members->businessLookups)->toBe([]);

        ($this->nothingWasWritten)();
    })->with([
        'a member that is not a uuid' => [teamMemberUpdate(), 'not-a-uuid', 'staff_member_not_found', DomainFailureKind::NotFound],
        'a blank name' => [teamMemberUpdate(['name' => '  ']), StaffFixtures::SECOND_MEMBER_ID, 'invalid_profile_name', DomainFailureKind::Invalid],
        'a name past the limit' => [teamMemberUpdate(['name' => str_repeat('a', 256)]), StaffFixtures::SECOND_MEMBER_ID, 'invalid_profile_name', DomainFailureKind::Invalid],
        'a job title past the limit' => [teamMemberUpdate(['job_title' => str_repeat('a', 121)]), StaffFixtures::SECOND_MEMBER_ID, 'invalid_profile_job_title', DomainFailureKind::Invalid],
        'a description past the limit' => [teamMemberUpdate(['about' => str_repeat('a', 1001)]), StaffFixtures::SECOND_MEMBER_ID, 'invalid_profile_about', DomainFailureKind::Invalid],
        'a phone with no number' => [teamMemberUpdate(['phone' => ['country_code' => 'MX']]), StaffFixtures::SECOND_MEMBER_ID, 'invalid_profile_phone', DomainFailureKind::Invalid],
        'the owner level' => [teamMemberUpdate(['level' => 'owner']), StaffFixtures::SECOND_MEMBER_ID, 'invalid_team_level', DomainFailureKind::Invalid],
        'an unknown level' => [teamMemberUpdate(['level' => 'admin']), StaffFixtures::SECOND_MEMBER_ID, 'invalid_team_level', DomainFailureKind::Invalid],
    ]);

    it('refuses a member nobody has, writing nothing', function () {
        $error = ($this->refusal)(teamMemberUpdate(), StaffFixtures::THIRD_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound);

        ($this->nothingWasWritten)();
    });

    it('refuses a member with no profile, writing nothing', function () {
        $this->profiles = (new FakeStaffProfileRepository($this->journal))->store(StaffFixtures::profile());

        $error = ($this->refusal)(teamMemberUpdate());

        expect($error->code)->toBe('staff_profile_not_found')
            ->and($error->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->journal->entries)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });

    it('refuses a country it cannot dial without consulting the parser, writing nothing', function () {
        $error = ($this->refusal)(teamMemberUpdate(['phone' => ['country_code' => 'XX', 'national_number' => '5512345678']]));

        expect($error->code)->toBe('invalid_profile_phone')
            ->and($this->parser->wasConsulted())->toBeFalse();

        ($this->nothingWasWritten)();
    });

    it('refuses a number the parser cannot dial, writing nothing', function () {
        $error = ($this->refusal)(teamMemberUpdate(['phone' => ['country_code' => 'MX', 'national_number' => '123']]));

        expect($error->code)->toBe('invalid_profile_phone')
            ->and($this->parser->wasConsulted())->toBeTrue();

        ($this->nothingWasWritten)();
    });
});

describe('refusals raised inside the transaction', function () {
    it('invites nobody when the account refuses the new name', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));
        $this->accounts->refuseRenameWith(InvalidProfileName::rejectedByAccount(InvalidAccountName::empty()));

        $error = ($this->refusal)(teamMemberUpdate(['level' => 'staff']));

        expect($error->code)->toBe('invalid_profile_name')
            ->and($this->journal->entries)->toBe(['accounts.rename'])
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->provisioner->issued)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });

    it('invites nobody when no temporary password can be issued', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));
        $this->provisioner->refuseIssuingWith(StaffMemberNotFound::forAccount(StaffFixtures::SECOND_ACCOUNT_ID));

        $error = ($this->refusal)(['level' => 'staff']);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($this->journal->outsideTransaction)->toBe([])
            ->and($this->dispatched)->toBe([]);
    });

    it('invites nobody when the commit itself fails', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));
        $this->transactions->failAtCommit(new RuntimeException('serialization failure'));

        expect(fn () => ($this->update)(['level' => 'staff']))->toThrow(RuntimeException::class, 'serialization failure')
            ->and($this->dispatched)->toBe([]);
    });
});

describe('after the commit', function () {
    beforeEach(function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::SECOND_MEMBER_ID, accountId: StaffFixtures::SECOND_ACCOUNT_ID, role: StaffRole::NoAccess));
    });

    it('announces the invitation before describing the member', function () {
        $directory = $this->accounts;
        $journal = $this->journal;
        $this->presenterAccounts = Mockery::mock(AccountDirectory::class);
        $this->presenterAccounts->shouldReceive('describe')->andReturnUsing(function (array $accountIds) use ($directory, $journal): array {
            $journal->record('presenter.describe');

            return $directory->describe($accountIds);
        });

        ($this->update)(['level' => 'staff'])->value();

        expect($this->journal->entries)->toBe(['profiles.save', 'members.save', 'accounts.issue_password', 'events.dispatch', 'presenter.describe'])
            ->and($this->journal->outsideTransaction)->toBe(['events.dispatch', 'presenter.describe']);
    });

    it('lets a presenter refusal escape with the invitation already announced, rather than refusing a member that was written', function () {
        $this->presenterAccounts = new FakeAccountDirectory;

        expect(fn () => ($this->update)(['level' => 'staff']))->toThrow(StaffMemberNotFound::class)
            ->and($this->transactions->runs())->toBe(1)
            ->and($this->members->saved)->toHaveCount(1)
            ->and($this->members->saved[0]->role())->toBe(StaffRole::Member)
            ->and($this->dispatched)->toHaveCount(1)
            ->and($this->dispatched[0])->toBeInstanceOf(TeamMemberInvited::class)
            ->and($this->dispatched[0]->staffMemberId)->toBe(StaffFixtures::SECOND_MEMBER_ID)
            ->and($this->presenterAccounts->lastCall())->toBe([StaffFixtures::SECOND_ACCOUNT_ID]);
    });

    it('lets a presenter refusal escape when nothing was announced, rather than refusing a profile that was saved', function () {
        $this->presenterAccounts = new FakeAccountDirectory;

        expect(fn () => ($this->update)(['job_title' => 'Colorista']))->toThrow(StaffMemberNotFound::class)
            ->and($this->profiles->saved)->toHaveCount(1)
            ->and($this->profiles->saved[0]->jobTitle()?->value)->toBe('Colorista')
            ->and($this->dispatched)->toBe([]);
    });

    it('lets a listener failure escape before the member is described', function () {
        $events = Mockery::mock(Dispatcher::class);
        $events->shouldReceive('dispatch')->andThrow(new RuntimeException('the mail queue is down'));
        $this->events = $events;

        expect(fn () => ($this->update)(['level' => 'staff']))->toThrow(RuntimeException::class, 'the mail queue is down')
            ->and($this->members->saved)->toHaveCount(1)
            ->and($this->presenterAccounts->callCount())->toBe(0);
    });
});

describe('tenant isolation', function () {
    it('refuses a member of another business, looking it up only in the business of the context', function () {
        $this->members->store(StaffFixtures::member(id: StaffFixtures::THIRD_MEMBER_ID, businessId: StaffFixtures::OTHER_BUSINESS_ID, role: StaffRole::Member));

        $error = ($this->refusal)(teamMemberUpdate(), StaffFixtures::THIRD_MEMBER_ID);

        expect($error->code)->toBe('staff_member_not_found')
            ->and($this->members->businessLookups)->toBe([[
                'businessId' => FakeBusinessContext::BUSINESS_ID,
                'id' => StaffFixtures::THIRD_MEMBER_ID,
            ]]);

        ($this->nothingWasWritten)();
    });

    it('updates the member of the business in context and announces the invitation there', function () {
        $this->members->store(StaffFixtures::member(
            id: StaffFixtures::THIRD_MEMBER_ID,
            accountId: StaffFixtures::SECOND_ACCOUNT_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
            role: StaffRole::NoAccess,
        ));
        $this->profiles->store(StaffFixtures::profile(
            id: StaffFixtures::THIRD_PROFILE_ID,
            staffMemberId: StaffFixtures::THIRD_MEMBER_ID,
            businessId: StaffFixtures::OTHER_BUSINESS_ID,
        ));

        $data = ($this->update)(
            ['job_title' => 'Colorista', 'level' => 'staff'],
            StaffFixtures::THIRD_MEMBER_ID,
            ($this->build)(new FakeBusinessContext(StaffFixtures::OTHER_BUSINESS_ID)),
        )->value();

        expect($data->id)->toBe(StaffFixtures::THIRD_MEMBER_ID)
            ->and($this->profiles->saved[0]->id)->toBe(StaffFixtures::THIRD_PROFILE_ID)
            ->and($this->profiles->stored(StaffFixtures::SECOND_PROFILE_ID)?->jobTitle()?->value)->toBe(StaffFixtures::JOB_TITLE)
            ->and($this->dispatched[0]->businessId)->toBe(StaffFixtures::OTHER_BUSINESS_ID);
    });
});
