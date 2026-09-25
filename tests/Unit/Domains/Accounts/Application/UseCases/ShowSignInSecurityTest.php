<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\PasskeyData;
use App\Domains\Accounts\Application\Dtos\ShowSignInSecurityInput;
use App\Domains\Accounts\Application\Dtos\SignInSecurityData;
use App\Domains\Accounts\Application\UseCases\ShowSignInSecurity;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use App\Shared\ValueObjects\DomainFailureKind;
use Tests\Support\Accounts\FakePasskeyDirectory;
use Tests\Support\Accounts\SignInSecurityFixtures;

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->accounts->shouldNotReceive('save');
    $this->passkeys = new FakePasskeyDirectory;

    $this->useCase = new ShowSignInSecurity($this->accounts, $this->passkeys);

    $this->holding = function (Account $account): void {
        $this->accounts->shouldReceive('findById')->once()
            ->with(SignInSecurityFixtures::ACCOUNT_ID)
            ->andReturn($account);
    };

    $this->show = fn (): SignInSecurityData => $this->useCase
        ->handle(new ShowSignInSecurityInput(SignInSecurityFixtures::ACCOUNT_ID))
        ->value();
});

describe('a signed-in account', function () {
    it('describes its password, its two-factor status and its passkeys field by field', function () {
        ($this->holding)(SignInSecurityFixtures::account(PasswordStatus::Chosen, TwoFactorStatus::Enabled));
        $this->passkeys->register(
            SignInSecurityFixtures::ACCOUNT_ID,
            SignInSecurityFixtures::passkey(),
            SignInSecurityFixtures::passkey(
                id: SignInSecurityFixtures::SECOND_PASSKEY_ID,
                name: SignInSecurityFixtures::SECOND_PASSKEY_NAME,
                authenticator: null,
                lastUsedAt: null,
            ),
        );

        $security = ($this->show)();

        expect($security)->toBeInstanceOf(SignInSecurityData::class)
            ->and($security->hasPassword)->toBeTrue()
            ->and($security->twoFactor)->toBe(TwoFactorStatus::Enabled)
            ->and($security->passkeys)->toHaveCount(2)
            ->and($security->passkeys[0])->toBeInstanceOf(PasskeyData::class)
            ->and($security->passkeys[0]->id)->toBe(SignInSecurityFixtures::PASSKEY_ID)
            ->and($security->passkeys[0]->name)->toBe(SignInSecurityFixtures::PASSKEY_NAME)
            ->and($security->passkeys[0]->authenticator)->toBe(SignInSecurityFixtures::AUTHENTICATOR)
            ->and($security->passkeys[0]->createdAt->format(DATE_ATOM))->toBe(SignInSecurityFixtures::CREATED_AT)
            ->and($security->passkeys[0]->lastUsedAt->format(DATE_ATOM))->toBe(SignInSecurityFixtures::LAST_USED_AT)
            ->and($security->passkeys[1]->id)->toBe(SignInSecurityFixtures::SECOND_PASSKEY_ID)
            ->and($security->passkeys[1]->name)->toBe(SignInSecurityFixtures::SECOND_PASSKEY_NAME)
            ->and($security->passkeys[1]->authenticator)->toBeNull()
            ->and($security->passkeys[1]->lastUsedAt)->toBeNull();
    });

    it('says whether the account holds a password of any kind', function (PasswordStatus $status, bool $hasPassword) {
        ($this->holding)(SignInSecurityFixtures::account($status));

        expect(($this->show)()->hasPassword)->toBe($hasPassword);
    })->with([
        'signs in only through Google' => [PasswordStatus::Absent, false],
        'a temporary password still unchanged' => [PasswordStatus::Temporary, true],
        'a password the person chose' => [PasswordStatus::Chosen, true],
    ]);

    it('reports the stored two-factor status unchanged', function (TwoFactorStatus $status) {
        ($this->holding)(SignInSecurityFixtures::account(twoFactorStatus: $status));

        expect(($this->show)()->twoFactor)->toBe($status);
    })->with([TwoFactorStatus::Disabled, TwoFactorStatus::Pending, TwoFactorStatus::Enabled]);

    it('lists no passkeys for an account that registered none', function () {
        ($this->holding)(SignInSecurityFixtures::account());

        expect(($this->show)()->passkeys)->toBe([]);
    });

    it('lists only its own passkeys, never another account\'s', function () {
        ($this->holding)(SignInSecurityFixtures::account());
        $this->passkeys->register(SignInSecurityFixtures::ACCOUNT_ID, SignInSecurityFixtures::passkey());
        $this->passkeys->register(
            SignInSecurityFixtures::OTHER_ACCOUNT_ID,
            SignInSecurityFixtures::passkey(id: SignInSecurityFixtures::FOREIGN_PASSKEY_ID, name: 'Someone else'),
        );

        $security = ($this->show)();

        expect(array_map(static fn (PasskeyData $passkey): string => $passkey->id, $security->passkeys))
            ->toBe([SignInSecurityFixtures::PASSKEY_ID])
            ->and($this->passkeys->lookups)->toBe([SignInSecurityFixtures::ACCOUNT_ID]);
    });

    it('keeps the order the directory returned', function () {
        ($this->holding)(SignInSecurityFixtures::account());
        $this->passkeys->register(
            SignInSecurityFixtures::ACCOUNT_ID,
            SignInSecurityFixtures::passkey(id: SignInSecurityFixtures::SECOND_PASSKEY_ID),
            SignInSecurityFixtures::passkey(id: SignInSecurityFixtures::PASSKEY_ID),
        );

        expect(array_map(static fn (PasskeyData $passkey): string => $passkey->id, ($this->show)()->passkeys))
            ->toBe([SignInSecurityFixtures::SECOND_PASSKEY_ID, SignInSecurityFixtures::PASSKEY_ID]);
    });
});

describe('an account id that is not a uuid', function () {
    it('answers account_not_found without looking anything up', function (string $accountId) {
        $this->accounts->shouldNotReceive('findById');

        $response = $this->useCase->handle(new ShowSignInSecurityInput($accountId));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($this->passkeys->lookups)->toBe([]);
    })->with([
        'empty' => '',
        'an internal integer key' => '7',
        'garbage' => 'not-a-uuid',
    ]);
});

describe('an account that no longer exists', function () {
    it('answers account_not_found as not found, carrying the repository refusal', function () {
        $missing = AccountNotFound::withId(SignInSecurityFixtures::ACCOUNT_ID);
        $this->accounts->shouldReceive('findById')->once()
            ->with(SignInSecurityFixtures::ACCOUNT_ID)
            ->andThrow($missing);

        $response = $this->useCase->handle(new ShowSignInSecurityInput(SignInSecurityFixtures::ACCOUNT_ID));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->cause())->toBe($missing);
    });

    it('never asks the directory for passkeys', function () {
        $this->accounts->shouldReceive('findById')->once()
            ->andThrow(AccountNotFound::withId(SignInSecurityFixtures::ACCOUNT_ID));

        $this->useCase->handle(new ShowSignInSecurityInput(SignInSecurityFixtures::ACCOUNT_ID));

        expect($this->passkeys->lookups)->toBe([]);
    });
});
