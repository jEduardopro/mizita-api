<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\UseCases\IssueTemporaryPassword;
use App\Domains\Accounts\Application\UseCases\ProvisionAccessLessAccount;
use App\Domains\Accounts\Application\UseCases\ProvisionInvitedAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\PasswordHasher;
use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\TemporaryPassword;
use App\Domains\Staff\Exceptions\InvalidProfileName;
use App\Domains\Staff\Exceptions\InvalidTeamMemberEmail;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\Infrastructure\Gateways\AccountsTeamAccountProvisioner;
use App\Domains\Staff\ValueObjects\ProvisionedAccount;
use App\Domains\Staff\ValueObjects\StaffRole;
use Tests\Support\Accounts\FakeTemporaryPasswordVault;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;
use Tests\Support\Staff\StaffFixtures;

const PROVISIONED_TEMPORARY_PASSWORD = 'Tmp-Pa55word-0123456789';

function existingTeamAccount(PasswordStatus $status): Account
{
    return Account::restore(
        id: StaffFixtures::SECOND_ACCOUNT_ID,
        name: 'Grace Hopper',
        email: 'grace@example.com',
        emailVerifiedAt: StaffFixtures::now(),
        createdAt: StaffFixtures::now(),
        passwordStatus: $status,
    );
}

function provisionerRefusalFrom(callable $work): ?Throwable
{
    try {
        $work();
    } catch (Throwable $thrown) {
        return $thrown;
    }

    return null;
}

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->passwords = Mockery::mock(TemporaryPasswordGenerator::class);
    $this->passwords->shouldReceive('generate')->andReturn(TemporaryPassword::fromString(PROVISIONED_TEMPORARY_PASSWORD))->byDefault();
    $this->hasher = Mockery::mock(PasswordHasher::class);
    $this->hasher->shouldReceive('hash')->andReturn('hashed')->byDefault();

    $ids = new FixedIdGenerator(StaffFixtures::SECOND_ACCOUNT_ID);
    $clock = new FakeClock(StaffFixtures::now());
    $transactions = new FakeTransactionManager;
    $this->vault = new FakeTemporaryPasswordVault;

    $this->provisioner = new AccountsTeamAccountProvisioner(
        new ProvisionInvitedAccount($this->accounts, $this->passwords, $this->vault, $this->hasher, $ids, $clock, $transactions),
        new ProvisionAccessLessAccount($this->accounts, $ids, $clock, $transactions),
        new IssueTemporaryPassword($this->accounts, $this->passwords, $this->vault, $this->hasher, $transactions),
    );
});

describe('provisioning a new account', function () {
    it('creates an account holding a temporary password for a member given access, and hands the password back', function () {
        $this->accounts->shouldReceive('findByEmail')->once()->with('grace@example.com')->andReturn(null);
        $saved = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));
        $this->accounts->shouldNotReceive('findById');

        $provisioned = $this->provisioner->provision(StaffRole::Member, 'Grace Hopper', 'grace@example.com');

        expect($provisioned)->toBeInstanceOf(ProvisionedAccount::class)
            ->and($provisioned->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($provisioned->temporaryPassword)->toBe(PROVISIONED_TEMPORARY_PASSWORD)
            ->and($saved)->toBeInstanceOf(Account::class)
            ->and($saved->id)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($this->vault->reveal(StaffFixtures::SECOND_ACCOUNT_ID))->toBe(PROVISIONED_TEMPORARY_PASSWORD);
    });

    it('creates an account with no password at all for a no access member', function () {
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once();
        $this->passwords->shouldNotReceive('generate');
        $this->accounts->shouldNotReceive('findById');

        $provisioned = $this->provisioner->provision(StaffRole::NoAccess, 'Linus Pauling', 'linus@example.com');

        expect($provisioned->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($provisioned->temporaryPassword)->toBeNull()
            ->and($this->vault->kept)->toBe([]);
    });
});

describe('provisioning an account that already exists', function () {
    it('issues a temporary password to an existing account given access that never chose one', function (PasswordStatus $status) {
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(existingTeamAccount($status));
        $this->accounts->shouldReceive('findById')->once()->with(StaffFixtures::SECOND_ACCOUNT_ID)->andReturn(existingTeamAccount($status));
        $this->accounts->shouldReceive('save')->once();

        $provisioned = $this->provisioner->provision(StaffRole::Member, 'Grace Hopper', 'grace@example.com');

        expect($provisioned->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($provisioned->temporaryPassword)->toBe(PROVISIONED_TEMPORARY_PASSWORD)
            ->and($this->vault->reveal(StaffFixtures::SECOND_ACCOUNT_ID))->toBe(PROVISIONED_TEMPORARY_PASSWORD);
    })->with([
        'no password' => PasswordStatus::Absent,
        'a temporary password' => PasswordStatus::Temporary,
    ]);

    it('sends no password to an existing account that chose its own', function () {
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(existingTeamAccount(PasswordStatus::Chosen));
        $this->accounts->shouldReceive('findById')->once()->andReturn(existingTeamAccount(PasswordStatus::Chosen));
        $this->accounts->shouldNotReceive('save');
        $this->passwords->shouldNotReceive('generate');

        expect($this->provisioner->provision(StaffRole::Member, 'Grace Hopper', 'grace@example.com')->temporaryPassword)->toBeNull();

        expect($this->vault->kept)->toBe([]);
    });

    it('never issues a password to an existing account joining with no access', function () {
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(existingTeamAccount(PasswordStatus::Absent));
        $this->accounts->shouldNotReceive('findById');
        $this->accounts->shouldNotReceive('save');
        $this->passwords->shouldNotReceive('generate');

        $provisioned = $this->provisioner->provision(StaffRole::NoAccess, 'Grace Hopper', 'grace@example.com');

        expect($provisioned->accountId)->toBe(StaffFixtures::SECOND_ACCOUNT_ID)
            ->and($provisioned->temporaryPassword)->toBeNull()
            ->and($this->vault->kept)->toBe([]);
    });
});

describe('translating refusals', function () {
    it('translates a name the account refuses into the staff refusal, keeping the cause', function (StaffRole $level) {
        $this->accounts->shouldNotReceive('findByEmail');

        $thrown = provisionerRefusalFrom(fn () => $this->provisioner->provision($level, '   ', 'grace@example.com'));

        expect($thrown)->toBeInstanceOf(InvalidProfileName::class)
            ->and($thrown?->getPrevious())->toBeInstanceOf(InvalidAccountName::class);
    })->with([
        'staff' => StaffRole::Member,
        'no access' => StaffRole::NoAccess,
    ]);

    it('translates an email the account refuses into the staff refusal, keeping the cause', function () {
        $this->accounts->shouldNotReceive('findByEmail');

        $thrown = provisionerRefusalFrom(fn () => $this->provisioner->provision(StaffRole::Member, 'Grace Hopper', '   '));

        expect($thrown)->toBeInstanceOf(InvalidTeamMemberEmail::class)
            ->and($thrown?->getPrevious())->toBeInstanceOf(InvalidAccountEmail::class);
    });
});

describe('issuing a temporary password', function () {
    it('issues a fresh temporary password to an account that never chose one', function () {
        $this->accounts->shouldReceive('findById')->once()->with(StaffFixtures::SECOND_ACCOUNT_ID)->andReturn(existingTeamAccount(PasswordStatus::Temporary));
        $this->accounts->shouldReceive('save')->once();

        expect($this->provisioner->issueTemporaryPassword(StaffFixtures::SECOND_ACCOUNT_ID))->toBe(PROVISIONED_TEMPORARY_PASSWORD);

        expect($this->vault->reveal(StaffFixtures::SECOND_ACCOUNT_ID))->toBe(PROVISIONED_TEMPORARY_PASSWORD);
    });

    it('issues nothing to an account that chose its own password', function () {
        $this->accounts->shouldReceive('findById')->once()->andReturn(existingTeamAccount(PasswordStatus::Chosen));
        $this->accounts->shouldNotReceive('save');

        expect($this->provisioner->issueTemporaryPassword(StaffFixtures::SECOND_ACCOUNT_ID))->toBeNull();
    });

    it('translates an account that is gone into a staff member that is not there, keeping the cause', function () {
        $missing = AccountNotFound::withId(StaffFixtures::SECOND_ACCOUNT_ID);
        $this->accounts->shouldReceive('findById')->once()->andThrow($missing);

        $thrown = provisionerRefusalFrom(fn () => $this->provisioner->issueTemporaryPassword(StaffFixtures::SECOND_ACCOUNT_ID));

        expect($thrown)->toBeInstanceOf(StaffMemberNotFound::class)
            ->and($thrown?->getPrevious())->toBe($missing);
    });
});
