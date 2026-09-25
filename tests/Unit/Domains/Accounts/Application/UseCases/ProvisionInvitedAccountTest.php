<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\InvitedAccountData;
use App\Domains\Accounts\Application\Dtos\ProvisionInvitedAccountInput;
use App\Domains\Accounts\Application\UseCases\ProvisionInvitedAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\PasswordHasher;
use App\Domains\Accounts\Contracts\TemporaryPasswordGenerator;
use App\Domains\Accounts\Contracts\TemporaryPasswordVault;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\TemporaryPasswordTooShort;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\InvitationFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->temporaryPasswords = Mockery::mock(TemporaryPasswordGenerator::class);
    $this->vault = Mockery::mock(TemporaryPasswordVault::class);
    $this->hasher = Mockery::mock(PasswordHasher::class);
    $this->transactions = new FakeTransactionManager;

    $this->useCase = new ProvisionInvitedAccount(
        $this->accounts,
        $this->temporaryPasswords,
        $this->vault,
        $this->hasher,
        new FixedIdGenerator(InvitationFixtures::GENERATED_ACCOUNT_ID),
        new FakeClock(InvitationFixtures::now()),
        $this->transactions,
    );

    $this->issuesTheTemporaryPassword = function (): void {
        $this->temporaryPasswords->shouldReceive('generate')->once()->andReturn(InvitationFixtures::temporaryPassword());
        $this->hasher->shouldReceive('hash')->once()
            ->with(InvitationFixtures::TEMPORARY_PASSWORD)
            ->andReturn(InvitationFixtures::TEMPORARY_PASSWORD_HASH);
    };

    $this->issuesNoPassword = function (): void {
        $this->temporaryPasswords->shouldNotReceive('generate');
        $this->hasher->shouldNotReceive('hash');
        $this->vault->shouldNotReceive('keep');
    };
});

describe('an email nobody has registered', function () {
    beforeEach(function () {
        $this->accounts->shouldReceive('findByEmail')->once()->with(InvitationFixtures::EMAIL)->andReturnNull();
        ($this->issuesTheTemporaryPassword)();
        $this->steps = [];
        $this->vault->shouldReceive('keep')->once()
            ->with(InvitationFixtures::GENERATED_ACCOUNT_ID, InvitationFixtures::TEMPORARY_PASSWORD)
            ->andReturnUsing(function (): void {
                $this->steps[] = ['keep', $this->transactions->isRunning()];
            });
    });

    it('creates the account and hands back its uuid and the plaintext temporary password', function () {
        $this->accounts->shouldReceive('save')->once();

        $invited = $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL))->value();

        expect($invited)->toBeInstanceOf(InvitedAccountData::class)
            ->and($invited->accountId)->toBe(InvitationFixtures::GENERATED_ACCOUNT_ID)
            ->and($invited->temporaryPassword)->toBe(InvitationFixtures::TEMPORARY_PASSWORD)
            ->and($invited->created)->toBeTrue();
    });

    it('saves an unverified account holding only the hash, flagged to change it', function () {
        $saved = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL));

        expect($saved)->toBeInstanceOf(Account::class)
            ->and($saved->id)->toBe(InvitationFixtures::GENERATED_ACCOUNT_ID)
            ->and($saved->name())->toBe(InvitationFixtures::NAME)
            ->and($saved->email())->toBe(InvitationFixtures::EMAIL)
            ->and($saved->emailVerifiedAt())->toBeNull()
            ->and($saved->createdAt)->toEqual(InvitationFixtures::now())
            ->and($saved->mustChangePassword())->toBeTrue()
            ->and($saved->issuedPasswordHash())->toBe(InvitationFixtures::TEMPORARY_PASSWORD_HASH)
            ->and($saved->issuedPasswordHash())->not->toBe(InvitationFixtures::TEMPORARY_PASSWORD);
    });

    it('saves inside one transaction', function () {
        $savedInsideTransaction = null;
        $this->accounts->shouldReceive('save')->once()->andReturnUsing(function () use (&$savedInsideTransaction): void {
            $savedInsideTransaction = $this->transactions->isRunning();
        });

        $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL));

        expect($savedInsideTransaction)->toBeTrue()
            ->and($this->transactions->runs())->toBe(1);
    });

    it('keeps the plaintext for the owner to copy after the save, inside the same transaction', function () {
        $this->accounts->shouldReceive('save')->once()->andReturnUsing(function (): void {
            $this->steps[] = ['save', $this->transactions->isRunning()];
        });

        $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL));

        expect($this->steps)->toBe([['save', true], ['keep', true]])
            ->and($this->transactions->runs())->toBe(1);
    });

    it('looks the email up the way it will be stored, trimmed and lowercased', function () {
        $saved = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $response = $this->useCase->handle(new ProvisionInvitedAccountInput('  Ada Lovelace  ', '  ADA@Example.com '));

        expect($response->succeeded())->toBeTrue()
            ->and($saved->name())->toBe(InvitationFixtures::NAME)
            ->and($saved->email())->toBe(InvitationFixtures::EMAIL);
    });
});

describe('an email that already has an account', function () {
    it('returns the existing account untouched, issuing no password and saving nothing', function (PasswordStatus $status) {
        $this->accounts->shouldReceive('findByEmail')->once()->with(InvitationFixtures::EMAIL)
            ->andReturn(InvitationFixtures::storedAccount($status));
        ($this->issuesNoPassword)();
        $this->accounts->shouldNotReceive('save');

        $invited = $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL))->value();

        expect($invited->accountId)->toBe(InvitationFixtures::EXISTING_ACCOUNT_ID)
            ->and($invited->temporaryPassword)->toBeNull()
            ->and($invited->created)->toBeFalse()
            ->and($this->transactions->runs())->toBe(0);
    })->with([
        'with no password' => PasswordStatus::Absent,
        'with a temporary password' => PasswordStatus::Temporary,
        'with a chosen password' => PasswordStatus::Chosen,
    ]);
});

describe('losing the race to a concurrent registration of the same email', function () {
    beforeEach(function () {
        ($this->issuesTheTemporaryPassword)();
        $this->conflict = AccountAlreadyRegistered::withEmail(InvitationFixtures::EMAIL);
        $this->accounts->shouldReceive('save')->once()->andThrow($this->conflict);
        $this->vault->shouldNotReceive('keep');
    });

    it('re-reads the winner and returns it as an existing account, withholding the password nobody stored', function () {
        $this->accounts->shouldReceive('findByEmail')->twice()->with(InvitationFixtures::EMAIL)
            ->andReturn(null, InvitationFixtures::storedAccount());

        $invited = $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL))->value();

        expect($invited->accountId)->toBe(InvitationFixtures::EXISTING_ACCOUNT_ID)
            ->and($invited->temporaryPassword)->toBeNull()
            ->and($invited->created)->toBeFalse();
    });

    it('answers with the conflict when the re-read finds nothing either', function () {
        $this->accounts->shouldReceive('findByEmail')->twice()->with(InvitationFixtures::EMAIL)->andReturnNull();

        $response = $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_already_registered')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause())->toBe($this->conflict);
    });
});

describe('a payload it cannot provision from', function () {
    it('answers with the refusal before touching any port', function (string $name, string $email, string $code) {
        $this->accounts->shouldNotReceive('findByEmail');
        $this->accounts->shouldNotReceive('save');
        ($this->issuesNoPassword)();

        $response = $this->useCase->handle(new ProvisionInvitedAccountInput($name, $email));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($this->transactions->runs())->toBe(0);
    })->with([
        'blank name' => ['   ', InvitationFixtures::EMAIL, 'invalid_account_name'],
        'name past the limit' => [str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1), InvitationFixtures::EMAIL, 'invalid_account_name'],
        'blank email' => [InvitationFixtures::NAME, '', 'invalid_account_email'],
        'malformed email' => [InvitationFixtures::NAME, 'ada.example.com', 'invalid_account_email'],
    ]);
});

it('lets a generator that breaks its own length contract escape as a programmer error', function () {
    $bug = TemporaryPasswordTooShort::belowMinimum(16);
    $this->accounts->shouldReceive('findByEmail')->once()->andReturnNull();
    $this->temporaryPasswords->shouldReceive('generate')->once()->andThrow($bug);
    $this->hasher->shouldNotReceive('hash');
    $this->accounts->shouldNotReceive('save');
    $this->vault->shouldNotReceive('keep');

    expect(fn () => $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL)))
        ->toThrow($bug);
});

it('answers with not found, handing back no password, when the vault cannot find the account it just saved', function () {
    $missing = AccountNotFound::withId(InvitationFixtures::GENERATED_ACCOUNT_ID);
    $this->accounts->shouldReceive('findByEmail')->once()->andReturnNull();
    ($this->issuesTheTemporaryPassword)();
    $this->accounts->shouldReceive('save')->once();
    $this->vault->shouldReceive('keep')->once()->andThrow($missing);

    $response = $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('account_not_found')
        ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
        ->and($response->error()->cause())->toBe($missing);
});
