<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\InvitedAccountData;
use App\Domains\Accounts\Application\Dtos\ProvisionInvitedAccountInput;
use App\Domains\Accounts\Application\UseCases\ProvisionAccessLessAccount;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\InvitationFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->transactions = new FakeTransactionManager;

    $this->useCase = new ProvisionAccessLessAccount(
        $this->accounts,
        new FixedIdGenerator(InvitationFixtures::GENERATED_ACCOUNT_ID),
        new FakeClock(InvitationFixtures::now()),
        $this->transactions,
    );
});

describe('an email nobody has registered', function () {
    beforeEach(function () {
        $this->accounts->shouldReceive('findByEmail')->once()->with(InvitationFixtures::EMAIL)->andReturnNull();
    });

    it('creates the account and hands back its uuid with no password', function () {
        $this->accounts->shouldReceive('save')->once();

        $invited = $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL))->value();

        expect($invited)->toBeInstanceOf(InvitedAccountData::class)
            ->and($invited->accountId)->toBe(InvitationFixtures::GENERATED_ACCOUNT_ID)
            ->and($invited->temporaryPassword)->toBeNull()
            ->and($invited->created)->toBeTrue();
    });

    it('saves an unverified account with no password and nothing to change', function () {
        $saved = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL));

        expect($saved)->toBeInstanceOf(Account::class)
            ->and($saved->id)->toBe(InvitationFixtures::GENERATED_ACCOUNT_ID)
            ->and($saved->name())->toBe(InvitationFixtures::NAME)
            ->and($saved->email())->toBe(InvitationFixtures::EMAIL)
            ->and($saved->emailVerifiedAt())->toBeNull()
            ->and($saved->createdAt)->toEqual(InvitationFixtures::now())
            ->and($saved->mustChangePassword())->toBeFalse()
            ->and($saved->issuedPasswordHash())->toBeNull()
            ->and($saved->acceptsTemporaryPassword())->toBeTrue();
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

    it('looks the email up the way it will be stored, trimmed and lowercased', function () {
        $saved = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($saved));

        $response = $this->useCase->handle(new ProvisionInvitedAccountInput("\tAda Lovelace ", ' Ada@EXAMPLE.com'));

        expect($response->succeeded())->toBeTrue()
            ->and($saved->name())->toBe(InvitationFixtures::NAME)
            ->and($saved->email())->toBe(InvitationFixtures::EMAIL);
    });
});

it('returns an existing account untouched and saves nothing', function (PasswordStatus $status) {
    $this->accounts->shouldReceive('findByEmail')->once()->with(InvitationFixtures::EMAIL)
        ->andReturn(InvitationFixtures::storedAccount($status));
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

describe('losing the race to a concurrent registration of the same email', function () {
    beforeEach(function () {
        $this->conflict = AccountAlreadyRegistered::withEmail(InvitationFixtures::EMAIL);
        $this->accounts->shouldReceive('save')->once()->andThrow($this->conflict);
    });

    it('re-reads the winner and returns it as an existing account', function () {
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

it('answers with the refusal before touching the repository', function (string $name, string $email, string $code) {
    $this->accounts->shouldNotReceive('findByEmail');
    $this->accounts->shouldNotReceive('save');

    $response = $this->useCase->handle(new ProvisionInvitedAccountInput($name, $email));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe($code)
        ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
        ->and($this->transactions->runs())->toBe(0);
})->with([
    'blank name' => ["\n", InvitationFixtures::EMAIL, 'invalid_account_name'],
    'name past the limit' => [str_repeat('a', Account::MAXIMUM_NAME_LENGTH + 1), InvitationFixtures::EMAIL, 'invalid_account_name'],
    'blank email' => [InvitationFixtures::NAME, '  ', 'invalid_account_email'],
    'malformed email' => [InvitationFixtures::NAME, 'ada@', 'invalid_account_email'],
]);

it('lets a programmer error escape rather than dressing it as a domain failure', function () {
    $bug = new RuntimeException('the users table is gone');
    $this->accounts->shouldReceive('findByEmail')->once()->andReturnNull();
    $this->accounts->shouldReceive('save')->once()->andThrow($bug);

    expect(fn () => $this->useCase->handle(new ProvisionInvitedAccountInput(InvitationFixtures::NAME, InvitationFixtures::EMAIL)))
        ->toThrow($bug);
});
