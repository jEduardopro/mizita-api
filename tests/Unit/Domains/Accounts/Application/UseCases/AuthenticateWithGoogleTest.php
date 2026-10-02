<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Application\UseCases\AuthenticateWithGoogle;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\SocialIdentityRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Events\AccountRegistered;
use App\Domains\Accounts\Events\SocialIdentityLinked;
use App\Domains\Accounts\Exceptions\AccountAlreadyRegistered;
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\GoogleEmailNotVerified;
use App\Domains\Accounts\Exceptions\SocialIdentityAlreadyLinked;
use App\Domains\Accounts\ValueObjects\PasswordStatus;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Accounts\AccountJournal;
use Tests\Support\Accounts\FakeAccountPasskeys;
use Tests\Support\Accounts\FakeAccountSessions;
use Tests\Support\Accounts\FakeTemporaryPasswordVault;
use Tests\Support\Accounts\GoogleFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->socialIdentities = Mockery::mock(SocialIdentityRepository::class);
    $this->events = Mockery::mock(Dispatcher::class);
    $this->transactions = new FakeTransactionManager;
    $this->journal = new AccountJournal;
    $this->temporaryPasswords = new FakeTemporaryPasswordVault($this->journal, $this->transactions);
    $this->passkeys = new FakeAccountPasskeys($this->journal, $this->transactions);
    $this->sessions = new FakeAccountSessions($this->journal, $this->transactions);

    $this->useCase = new AuthenticateWithGoogle(
        $this->accounts,
        $this->socialIdentities,
        $this->temporaryPasswords,
        $this->passkeys,
        $this->sessions,
        new FixedIdGenerator(GoogleFixtures::GENERATED_ACCOUNT_ID, GoogleFixtures::GENERATED_IDENTITY_ID),
        new FakeClock(GoogleFixtures::now()),
        $this->transactions,
        $this->events,
    );

    $this->revokedNothing = function (): void {
        expect($this->temporaryPasswords->discarded)->toBe([])
            ->and($this->passkeys->deletedFor)->toBe([])
            ->and($this->sessions->endedForAll)->toBe([]);
    };

    $this->insideTransaction = [];
    $this->recordTransactionState = function (): bool {
        $this->insideTransaction[] = $this->transactions->isRunning();

        return true;
    };
});

describe('a returning person whose Google account is already linked', function () {
    it('signs them in from the stored link without writing or announcing anything', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
            ->with(SocialProvider::Google, GoogleFixtures::SUB)
            ->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()
            ->with(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->andReturn(GoogleFixtures::storedAccount(emailVerifiedAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00')));

        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $data = $this->useCase->handle(GoogleFixtures::input())->value();

        expect($data)->toBeInstanceOf(AuthenticatedAccountData::class)
            ->and($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeFalse();
    });

    it('answers with a successful response that carries no warning', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->andReturn(GoogleFixtures::storedAccount());

        $response = $this->useCase->handle(GoogleFixtures::input());

        expect($response)->toBeInstanceOf(UseCaseResponse::class)
            ->and($response->succeeded())->toBeTrue()
            ->and($response->failed())->toBeFalse()
            ->and($response->warnings())->toBe([]);
    });

    it('opens no transaction at all', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->andReturn(GoogleFixtures::storedAccount());

        $this->useCase->handle(GoogleFixtures::input());

        expect($this->transactions->runs())->toBe(0);
    });

    it('revokes nothing and signs nobody out, even when the linked account was never verified', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->andReturn(
            GoogleFixtures::storedAccount(passwordStatus: PasswordStatus::Chosen, twoFactorStatus: TwoFactorStatus::Enabled),
        );

        $this->useCase->handle(GoogleFixtures::input());

        ($this->revokedNothing)();
    });

    it('finds them by subject even when the Google email no longer matches the stored one', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
            ->with(SocialProvider::Google, GoogleFixtures::SUB)
            ->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()
            ->andReturn(GoogleFixtures::storedAccount(email: 'ada.old@example.com'));

        $this->accounts->shouldNotReceive('findByEmail');
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $data = $this->useCase->handle(GoogleFixtures::input(email: 'ada.new@example.com'))->value();

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->email)->toBe('ada.old@example.com')
            ->and($data->isNewAccount)->toBeFalse();
    });

    it('signs them in even when Google reports the email as unverified', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()->andReturn(GoogleFixtures::storedAccount());

        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect($this->useCase->handle(GoogleFixtures::input(emailVerified: false))->value()->id)
            ->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID);
    });

    it('refuses with account_not_found when the link points at an account that is gone', function () {
        $missing = AccountNotFound::withId('vanished-account-uuid');

        $this->socialIdentities->shouldReceive('findByProviderUserId')
            ->andReturn(GoogleFixtures::storedIdentity('vanished-account-uuid'));
        $this->accounts->shouldReceive('findById')->once()
            ->with('vanished-account-uuid')
            ->andThrow($missing);

        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->cause())->toBe($missing)
            ->and($response->error()->cause()->getMessage())->toBe('Account [vanished-account-uuid] was not found.');
    });
});

describe('the account takeover guard', function () {
    beforeEach(function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
    });

    it('refuses an unverified Google email, naming the address in the cause', function () {
        $response = $this->useCase->handle(GoogleFixtures::input(emailVerified: false));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('google_email_not_verified')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
            ->and($response->error()->cause()->getMessage())
            ->toBe('Google has not verified the email address [ada@example.com].');
    });

    it('writes nothing when it refuses', function () {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');

        expect($this->useCase->handle(GoogleFixtures::input(emailVerified: false))->error()->code)
            ->toBe('google_email_not_verified');
    });

    it('announces nothing when it refuses', function () {
        $this->events->shouldNotReceive('dispatch');

        expect($this->useCase->handle(GoogleFixtures::input(emailVerified: false))->error()->code)
            ->toBe('google_email_not_verified');
    });

    it('never looks the address up, so an existing account is not even probed', function () {
        $this->accounts->shouldNotReceive('findByEmail');
        $this->accounts->shouldNotReceive('findById');

        expect($this->useCase->handle(GoogleFixtures::input(emailVerified: false))->error()->code)
            ->toBe('google_email_not_verified');
    });

    it('revokes nothing and signs nobody out when it refuses', function () {
        $this->useCase->handle(GoogleFixtures::input(emailVerified: false));

        ($this->revokedNothing)();
    });

    it('refuses before opening a transaction', function () {
        expect($this->useCase->handle(GoogleFixtures::input(emailVerified: false))->failed())->toBeTrue();

        expect($this->transactions->runs())->toBe(0);
    });

    it('carries no data behind the refusal', function () {
        $response = $this->useCase->handle(GoogleFixtures::input(emailVerified: false));

        expect($response->succeeded())->toBeFalse()
            ->and(fn () => $response->value())->toThrow(GoogleEmailNotVerified::class);
    });

    it('refuses whatever the address looks like', function (string $email) {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect($this->useCase->handle(GoogleFixtures::input(email: $email, emailVerified: false))->error()->code)
            ->toBe('google_email_not_verified');
    })->with([
        'a known address' => 'ada@example.com',
        'mixed case' => 'Ada@Example.com',
        'an address nobody uses' => 'nobody@example.com',
    ]);
});

describe('an existing account claimed by Google for the first time', function () {
    beforeEach(function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')
            ->with(SocialProvider::Google, GoogleFixtures::SUB)
            ->andReturn(null);
    });

    it('links Google to the account and returns it as an existing one', function () {
        $this->accounts->shouldReceive('findByEmail')->once()->with('ada@example.com')
            ->andReturn(GoogleFixtures::storedAccount(emailVerifiedAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00')));
        $this->accounts->shouldReceive('save')->once();

        $savedIdentity = null;
        $this->socialIdentities->shouldReceive('save')->once()->with(Mockery::capture($savedIdentity));

        $linkedEvent = null;
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::capture($linkedEvent));

        $data = $this->useCase->handle(GoogleFixtures::input())->value();

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeFalse();

        expect($savedIdentity)->toBeInstanceOf(SocialIdentity::class)
            ->and($savedIdentity->id)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($savedIdentity->accountId)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($savedIdentity->provider)->toBe(SocialProvider::Google)
            ->and($savedIdentity->providerUserId)->toBe(GoogleFixtures::SUB)
            ->and($savedIdentity->linkedAt)->toEqual(GoogleFixtures::now());

        expect($linkedEvent)->toBeInstanceOf(SocialIdentityLinked::class)
            ->and($linkedEvent->socialIdentityId)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($linkedEvent->accountId)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($linkedEvent->provider)->toBe(SocialProvider::Google);
    });

    it('announces the link and nothing else, because no registration happened', function () {
        $this->accounts->shouldReceive('findByEmail')->andReturn(GoogleFixtures::storedAccount());
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();

        $this->events->shouldNotReceive('dispatch')->with(Mockery::type(AccountRegistered::class));
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(SocialIdentityLinked::class));

        $this->useCase->handle(GoogleFixtures::input());
    });

    it('announces the link only after the transaction has committed', function () {
        $this->accounts->shouldReceive('findByEmail')->andReturn(GoogleFixtures::storedAccount());
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();

        $dispatchedInsideTransaction = null;
        $this->events->shouldReceive('dispatch')->once()
            ->andReturnUsing(function () use (&$dispatchedInsideTransaction): void {
                $dispatchedInsideTransaction = $this->transactions->isRunning();
            });

        $this->useCase->handle(GoogleFixtures::input());

        expect($dispatchedInsideTransaction)->toBeFalse();
    });

    it('matches the address case insensitively', function (string $googleEmail) {
        $this->accounts->shouldReceive('findByEmail')->once()->with('ada@example.com')
            ->andReturn(GoogleFixtures::storedAccount());
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once();

        expect($this->useCase->handle(GoogleFixtures::input(email: $googleEmail))->value()->isNewAccount)->toBeFalse();
    })->with([
        'mixed case' => 'Ada@Example.com',
        'upper case' => 'ADA@EXAMPLE.COM',
        'padded' => '  ada@example.com  ',
    ]);

    it('verifies the account email, because Google has just proven control of it', function () {
        $unverified = GoogleFixtures::storedAccount(emailVerifiedAt: null);
        $this->accounts->shouldReceive('findByEmail')->andReturn($unverified);

        $savedAccount = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($savedAccount));
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(GoogleFixtures::input());

        expect($savedAccount)->toBe($unverified)
            ->and($savedAccount->emailVerifiedAt())->toEqual(GoogleFixtures::now());
    });

    it('keeps the original verification timestamp when the email was already verified', function () {
        $verifiedAt = new DateTimeImmutable('2025-05-01T08:30:00+00:00');
        $this->accounts->shouldReceive('findByEmail')->andReturn(GoogleFixtures::storedAccount(emailVerifiedAt: $verifiedAt));

        $savedAccount = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($savedAccount));
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(GoogleFixtures::input());

        expect($savedAccount->emailVerifiedAt())->toEqual($verifiedAt);
    });

    it('claims inside a single transaction', function () {
        $this->accounts->shouldReceive('findByEmail')->andReturn(GoogleFixtures::storedAccount());
        $this->accounts->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->socialIdentities->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->events->shouldReceive('dispatch')->once();

        $this->useCase->handle(GoogleFixtures::input());

        expect($this->transactions->runs())->toBe(1)
            ->and($this->insideTransaction)->toBe([true, true]);
    });
});

describe('an unproven account claimed by Google', function () {
    beforeEach(function () {
        $this->unproven = GoogleFixtures::storedAccount(
            emailVerifiedAt: null,
            passwordStatus: PasswordStatus::Chosen,
            twoFactorStatus: TwoFactorStatus::Enabled,
        );
        $this->temporaryPasswords->holds(GoogleFixtures::EXISTING_ACCOUNT_ID, 'Tq7mW2xK9pLr4ZvB8nYd');
        $this->savedAccount = null;

        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->with('ada@example.com')->andReturn($this->unproven);
        $this->accounts->shouldReceive('save')->once()->andReturnUsing(function (Account $account): void {
            $this->journal->record('accounts.save');
            $this->savedAccount = $account;
        });
        $this->socialIdentities->shouldReceive('save')->once()->andReturnUsing(function (): void {
            $this->journal->record('socialIdentities.save');
        });
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(SocialIdentityLinked::class))
            ->andReturnUsing(function (): void {
                $this->journal->record('events.dispatch');
            });
    });

    it('saves the account with its password and its second factor revoked', function () {
        $this->useCase->handle(GoogleFixtures::input());

        expect($this->savedAccount)->toBe($this->unproven)
            ->and($this->savedAccount->emailVerifiedAt())->toEqual(GoogleFixtures::now())
            ->and($this->savedAccount->holdsPassword())->toBeFalse()
            ->and($this->savedAccount->twoFactorStatus())->toBe(TwoFactorStatus::Disabled)
            ->and($this->savedAccount->hasPendingAccessRevocation())->toBeTrue();
    });

    it('discards the temporary password an owner could still copy', function () {
        $this->useCase->handle(GoogleFixtures::input());

        expect($this->temporaryPasswords->discarded)->toBe([GoogleFixtures::EXISTING_ACCOUNT_ID])
            ->and($this->temporaryPasswords->reveal(GoogleFixtures::EXISTING_ACCOUNT_ID))->toBeNull();
    });

    it('deletes every passkey registered on the account', function () {
        $this->useCase->handle(GoogleFixtures::input());

        expect($this->passkeys->deletedFor)->toBe([GoogleFixtures::EXISTING_ACCOUNT_ID]);
    });

    it('revokes the temporary password and the passkeys inside the transaction that links Google', function () {
        $this->useCase->handle(GoogleFixtures::input());

        expect($this->transactions->runs())->toBe(1)
            ->and($this->temporaryPasswords->discardedInsideTransaction)->toBe([true])
            ->and($this->passkeys->deletedInsideTransaction)->toBe([true]);
    });

    it('signs the account out of every session once the claim has committed', function () {
        $this->useCase->handle(GoogleFixtures::input());

        expect($this->sessions->endedForAll)->toBe([[GoogleFixtures::EXISTING_ACCOUNT_ID]])
            ->and($this->sessions->endedInsideTransaction)->toBe([false])
            ->and($this->sessions->endedExcept)->toBe([]);
    });

    it('saves, links and revokes before it signs out, and signs out before it announces the link', function () {
        $this->useCase->handle(GoogleFixtures::input());

        expect($this->journal->entries)->toBe([
            'accounts.save',
            'socialIdentities.save',
            'temporaryPasswords.discard',
            'passkeys.deleteAllOf',
            'sessions.endAll',
            'events.dispatch',
        ]);
    });

    it('answers with the existing account and asks for no second factor it just turned off', function () {
        $data = $this->useCase->handle(GoogleFixtures::input())->value();

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeFalse()
            ->and($data->requiresSecondFactor)->toBeFalse();
    });
});

describe('an unproven claim that never commits', function () {
    beforeEach(function () {
        $this->unproven = GoogleFixtures::storedAccount(emailVerifiedAt: null, passwordStatus: PasswordStatus::Chosen);
    });

    it('signs nobody out when the link loses a race and the account is adopted instead', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->andReturn($this->unproven);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once()
            ->andThrow(SocialIdentityAlreadyLinked::forProviderUser(SocialProvider::Google, GoogleFixtures::SUB));
        $this->events->shouldNotReceive('dispatch');

        expect($this->useCase->handle(GoogleFixtures::input())->succeeded())->toBeTrue();

        ($this->revokedNothing)();
    });

    it('signs nobody out and announces nothing when the commit itself fails', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn($this->unproven);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldNotReceive('dispatch');
        $this->transactions->failAtCommit(new RuntimeException('SQLSTATE[40001] serialization failure'));

        expect(fn () => $this->useCase->handle(GoogleFixtures::input()))
            ->toThrow(RuntimeException::class, 'SQLSTATE[40001] serialization failure');

        expect($this->sessions->endedForAll)->toBe([]);
    });
});

describe('a proven owner claimed by Google', function () {
    beforeEach(function () {
        $this->proven = GoogleFixtures::storedAccount(
            emailVerifiedAt: new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            passwordStatus: PasswordStatus::Chosen,
            twoFactorStatus: TwoFactorStatus::Enabled,
        );
        $this->temporaryPasswords->holds(GoogleFixtures::EXISTING_ACCOUNT_ID, 'Tq7mW2xK9pLr4ZvB8nYd');
        $this->savedAccount = null;

        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn($this->proven);
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($this->savedAccount));
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::type(SocialIdentityLinked::class));
    });

    it('keeps the password and the second factor the owner set up', function () {
        $this->useCase->handle(GoogleFixtures::input());

        expect($this->savedAccount->holdsPassword())->toBeTrue()
            ->and($this->savedAccount->twoFactorStatus())->toBe(TwoFactorStatus::Enabled)
            ->and($this->savedAccount->hasPendingAccessRevocation())->toBeFalse();
    });

    it('discards no temporary password, deletes no passkey and signs nobody out', function () {
        $this->useCase->handle(GoogleFixtures::input());

        ($this->revokedNothing)();

        expect($this->temporaryPasswords->reveal(GoogleFixtures::EXISTING_ACCOUNT_ID))->toBe('Tq7mW2xK9pLr4ZvB8nYd');
    });

    it('still asks for the second factor', function () {
        expect($this->useCase->handle(GoogleFixtures::input())->value()->requiresSecondFactor)->toBeTrue();
    });
});

describe('a first-time visitor', function () {
    beforeEach(function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')
            ->with(SocialProvider::Google, GoogleFixtures::SUB)
            ->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->andReturn(null);
    });

    it('registers an account, links Google to it and reports it as new', function () {
        $savedAccount = null;
        $savedIdentity = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($savedAccount));
        $this->socialIdentities->shouldReceive('save')->once()->with(Mockery::capture($savedIdentity));
        $this->events->shouldReceive('dispatch')->twice();

        $data = $this->useCase->handle(GoogleFixtures::input(email: 'Ada@Example.com', name: '  Ada Lovelace  '))->value();

        expect($data)->toBeInstanceOf(AuthenticatedAccountData::class)
            ->and($data->id)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeTrue()
            ->and($data->requiresSecondFactor)->toBeFalse();

        expect($savedAccount)->toBeInstanceOf(Account::class)
            ->and($savedAccount->id)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($savedAccount->name())->toBe('Ada Lovelace')
            ->and($savedAccount->email())->toBe('ada@example.com')
            ->and($savedAccount->emailVerifiedAt())->toEqual(GoogleFixtures::now())
            ->and($savedAccount->createdAt)->toEqual(GoogleFixtures::now());

        expect($savedIdentity)->toBeInstanceOf(SocialIdentity::class)
            ->and($savedIdentity->id)->toBe(GoogleFixtures::GENERATED_IDENTITY_ID)
            ->and($savedIdentity->accountId)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($savedIdentity->provider)->toBe(SocialProvider::Google)
            ->and($savedIdentity->providerUserId)->toBe(GoogleFixtures::SUB)
            ->and($savedIdentity->linkedAt)->toEqual(GoogleFixtures::now());
    });

    it('announces the registration before the link', function () {
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();

        $registered = null;
        $linked = null;
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::capture($registered))->ordered();
        $this->events->shouldReceive('dispatch')->once()->with(Mockery::capture($linked))->ordered();

        $this->useCase->handle(GoogleFixtures::input());

        expect($registered)->toBeInstanceOf(AccountRegistered::class)
            ->and($registered->accountId)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($linked)->toBeInstanceOf(SocialIdentityLinked::class)
            ->and($linked->socialIdentityId)->toBe(GoogleFixtures::GENERATED_IDENTITY_ID)
            ->and($linked->accountId)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($linked->provider)->toBe(SocialProvider::Google);
    });

    it('announces both events only after the transaction has committed', function () {
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();

        $dispatchedInsideTransaction = [];
        $this->events->shouldReceive('dispatch')->twice()
            ->andReturnUsing(function () use (&$dispatchedInsideTransaction): void {
                $dispatchedInsideTransaction[] = $this->transactions->isRunning();
            });

        $this->useCase->handle(GoogleFixtures::input());

        expect($dispatchedInsideTransaction)->toBe([false, false]);
    });

    it('registers inside a single transaction', function () {
        $this->accounts->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->socialIdentities->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->events->shouldReceive('dispatch')->twice();

        $this->useCase->handle(GoogleFixtures::input());

        expect($this->transactions->runs())->toBe(1)
            ->and($this->insideTransaction)->toBe([true, true]);
    });

    it('revokes nothing and signs nobody out', function () {
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->twice();

        $this->useCase->handle(GoogleFixtures::input());

        ($this->revokedNothing)();
    });

    it('gives the account and the link distinct identifiers', function () {
        $savedAccount = null;
        $savedIdentity = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($savedAccount));
        $this->socialIdentities->shouldReceive('save')->once()->with(Mockery::capture($savedIdentity));
        $this->events->shouldReceive('dispatch')->twice();

        $this->useCase->handle(GoogleFixtures::input());

        expect($savedAccount->id)->not->toBe($savedIdentity->id);
    });

    it('refuses to register a blank name, writing and announcing nothing', function () {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input(name: '   '));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_account_name')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause()->getMessage())->toBe('An account name cannot be empty.');
    });

    it('refuses to register a malformed address, writing and announcing nothing', function () {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input(email: 'ada.example.com'));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_account_email')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause()->getMessage())->toBe('[ada.example.com] is not a valid email address.');
    });

    it('keeps accents in the name it registers', function () {
        $savedAccount = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($savedAccount));
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->twice();

        $data = $this->useCase->handle(GoogleFixtures::input(name: 'José Álvarez Muñoz'))->value();

        expect($savedAccount->name())->toBe('José Álvarez Muñoz')
            ->and($data->name)->toBe('José Álvarez Muñoz');
    });
});

describe('a race with a concurrent sign in', function () {
    it('adopts the winner account when the address was registered first', function () {
        $winner = GoogleFixtures::storedAccount(emailVerifiedAt: GoogleFixtures::now());

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()
            ->with(SocialProvider::Google, GoogleFixtures::SUB)
            ->andReturn(null, GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findByEmail')->once()->with('ada@example.com')->andReturn(null);
        $this->accounts->shouldReceive('save')->once()
            ->andThrow(AccountAlreadyRegistered::withEmail('ada@example.com'));
        $this->accounts->shouldReceive('findById')->once()
            ->with(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->andReturn($winner);

        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input());
        $data = $response->value();

        expect($response->succeeded())->toBeTrue()
            ->and($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeFalse();
    });

    it('recovers outside the transaction, which Postgres has already aborted', function () {
        $lookupsInsideTransaction = [];
        $recordLookup = function () use (&$lookupsInsideTransaction): void {
            $lookupsInsideTransaction[] = $this->transactions->isRunning();
        };

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()
            ->andReturnUsing(
                function () use ($recordLookup): ?SocialIdentity {
                    $recordLookup();

                    return null;
                },
                function () use ($recordLookup): ?SocialIdentity {
                    $recordLookup();

                    return GoogleFixtures::storedIdentity();
                },
            );
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once()
            ->andThrow(AccountAlreadyRegistered::withEmail('ada@example.com'));
        $this->accounts->shouldReceive('findById')->once()->andReturn(GoogleFixtures::storedAccount());
        $this->events->shouldNotReceive('dispatch');

        $this->useCase->handle(GoogleFixtures::input());

        expect($this->transactions->runs())->toBe(1)
            ->and($lookupsInsideTransaction)->toBe([false, false]);
    });

    it('adopts the account behind the address when the link was written first', function () {
        $existing = GoogleFixtures::storedAccount(emailVerifiedAt: GoogleFixtures::now());

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->with('ada@example.com')->andReturn($existing);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once()
            ->andThrow(SocialIdentityAlreadyLinked::forProviderUser(SocialProvider::Google, GoogleFixtures::SUB));

        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input());
        $data = $response->value();

        expect($response->succeeded())->toBeTrue()
            ->and($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeFalse();
    });

    it('announces nothing it did not commit', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()
            ->andReturn(null, GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once()
            ->andThrow(AccountAlreadyRegistered::withEmail('ada@example.com'));
        $this->accounts->shouldReceive('findById')->once()->andReturn(GoogleFixtures::storedAccount());

        $this->events->shouldNotReceive('dispatch')->with(Mockery::type(AccountRegistered::class));
        $this->events->shouldNotReceive('dispatch')->with(Mockery::type(SocialIdentityLinked::class));
        $this->events->shouldNotReceive('dispatch');

        $this->useCase->handle(GoogleFixtures::input());
    });

    it('reports the address conflict untouched when the rows are still not there', function () {
        $conflict = AccountAlreadyRegistered::withEmail('ada@example.com');

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->with('ada@example.com')->andReturn(null, null);
        $this->accounts->shouldReceive('save')->once()->andThrow($conflict);

        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_already_registered')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause())->toBe($conflict);
    });

    it('reports the link conflict untouched when the rows are still not there', function () {
        $conflict = SocialIdentityAlreadyLinked::forProviderUser(SocialProvider::Google, GoogleFixtures::SUB);

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->with('ada@example.com')->andReturn(null, null);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once()->andThrow($conflict);

        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('social_identity_already_linked')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause())->toBe($conflict);
    });

    it('rethrows the very exception it swallowed when the caller unwraps the refusal', function () {
        $conflict = AccountAlreadyRegistered::withEmail('ada@example.com');

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('save')->once()->andThrow($conflict);
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle(GoogleFixtures::input());
        $thrown = null;

        try {
            $response->value();
        } catch (Throwable $caught) {
            $thrown = $caught;
        }

        expect($thrown)->toBe($conflict);
    });

    it('announces no registration for work that was rolled back', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()
            ->andReturn(null, GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once()
            ->andThrow(SocialIdentityAlreadyLinked::forProviderUser(SocialProvider::Google, GoogleFixtures::SUB));
        $this->accounts->shouldReceive('findById')->once()->andReturn(GoogleFixtures::storedAccount());

        $this->events->shouldNotReceive('dispatch');

        $data = $this->useCase->handle(GoogleFixtures::input())->value();

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->isNewAccount)->toBeFalse();
    });
});

describe('an account scheduled for deletion', function () {
    beforeEach(function () {
        $this->scheduledAccount = Account::restore(
            GoogleFixtures::EXISTING_ACCOUNT_ID,
            GoogleFixtures::NAME,
            GoogleFixtures::EMAIL,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            deletionRequestedAt: new DateTimeImmutable('2025-12-20T09:15:00+00:00'),
        );

        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');
    });

    it('refuses a linked Google identity with account_pending_reactivation', function () {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()
            ->with(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->andReturn($this->scheduledAccount);

        $response = $this->useCase->handle(GoogleFixtures::input());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_pending_reactivation')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($response->error()->cause()->accountId)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID);
    });

    it('refuses to claim the account by its email, saving and linking nothing', function () {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->with('ada@example.com')->andReturn($this->scheduledAccount);

        $response = $this->useCase->handle(GoogleFixtures::input());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_pending_reactivation')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Conflict)
            ->and($this->scheduledAccount->isScheduledForDeletion())->toBeTrue();
    });

    it('leaves the email of a scheduled account unverified when it refuses the claim', function () {
        $unverified = Account::restore(
            GoogleFixtures::EXISTING_ACCOUNT_ID,
            GoogleFixtures::NAME,
            GoogleFixtures::EMAIL,
            null,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            deletionRequestedAt: new DateTimeImmutable('2025-12-20T09:15:00+00:00'),
        );
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn($unverified);
        $this->accounts->shouldNotReceive('save');

        $this->useCase->handle(GoogleFixtures::input());

        expect($unverified->emailVerifiedAt())->toBeNull();
    });

    it('revokes nothing on a scheduled account it refuses to claim', function () {
        $unverified = Account::restore(
            GoogleFixtures::EXISTING_ACCOUNT_ID,
            GoogleFixtures::NAME,
            GoogleFixtures::EMAIL,
            null,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            PasswordStatus::Chosen,
            deletionRequestedAt: new DateTimeImmutable('2025-12-20T09:15:00+00:00'),
        );
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn($unverified);
        $this->accounts->shouldNotReceive('save');

        $this->useCase->handle(GoogleFixtures::input());

        ($this->revokedNothing)();

        expect($unverified->holdsPassword())->toBeTrue();
    });

    it('refuses the account it would have adopted after losing a registration race', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->with('ada@example.com')
            ->andReturn(null, $this->scheduledAccount);
        $this->accounts->shouldReceive('save')->once()
            ->andThrow(AccountAlreadyRegistered::withEmail('ada@example.com'));

        $response = $this->useCase->handle(GoogleFixtures::input());

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_pending_reactivation');
    });
});

describe('whether the account still owes a second factor', function () {
    beforeEach(function () {
        $this->withTwoFactor = fn (TwoFactorStatus $status): Account => Account::restore(
            GoogleFixtures::EXISTING_ACCOUNT_ID,
            GoogleFixtures::NAME,
            GoogleFixtures::EMAIL,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            twoFactorStatus: $status,
        );
    });

    it('reads it off the account a stored link points at', function (TwoFactorStatus $status, bool $requiresSecondFactor) {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()->andReturn(($this->withTwoFactor)($status));

        expect($this->useCase->handle(GoogleFixtures::input())->value()->requiresSecondFactor)
            ->toBe($requiresSecondFactor);
    })->with([
        'enabled' => [TwoFactorStatus::Enabled, true],
        'set up but never confirmed' => [TwoFactorStatus::Pending, false],
        'disabled' => [TwoFactorStatus::Disabled, false],
    ]);

    it('reads it off an existing account Google claims for the first time', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(($this->withTwoFactor)(TwoFactorStatus::Enabled));
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once();

        $data = $this->useCase->handle(GoogleFixtures::input())->value();

        expect($data->isNewAccount)->toBeFalse()
            ->and($data->requiresSecondFactor)->toBeTrue();
    });

    it('drops it for an unproven account Google claims, because the claim turned it off', function () {
        $unproven = Account::restore(
            GoogleFixtures::EXISTING_ACCOUNT_ID,
            GoogleFixtures::NAME,
            GoogleFixtures::EMAIL,
            null,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            twoFactorStatus: TwoFactorStatus::Enabled,
        );
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn($unproven);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once();

        expect($this->useCase->handle(GoogleFixtures::input())->value()->requiresSecondFactor)->toBeFalse();
    });

    it('reads it off the account adopted after losing a registration race', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()
            ->andReturn(null, ($this->withTwoFactor)(TwoFactorStatus::Enabled));
        $this->accounts->shouldReceive('save')->once()->andThrow(AccountAlreadyRegistered::withEmail(GoogleFixtures::EMAIL));
        $this->events->shouldNotReceive('dispatch');

        expect($this->useCase->handle(GoogleFixtures::input())->value()->requiresSecondFactor)->toBeTrue();
    });
});

describe('a collaborator that fails outside the domain', function () {
    it('lets a storage failure escape instead of turning it into a refusal the client could read', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
            ->andThrow(new RuntimeException('SQLSTATE[08006] connection failure'));

        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input()))
            ->toThrow(RuntimeException::class, 'SQLSTATE[08006] connection failure');
    });

    it('lets a write failure escape from inside the transaction', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once()
            ->andThrow(new RuntimeException('SQLSTATE[40001] serialization failure'));

        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input()))
            ->toThrow(RuntimeException::class, 'SQLSTATE[40001] serialization failure');
    });

    it('lets a dispatcher failure escape, because the write is already committed', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once()->andThrow(new RuntimeException('the listener blew up'));

        expect(fn () => $this->useCase->handle(GoogleFixtures::input()))
            ->toThrow(RuntimeException::class, 'the listener blew up');
    });

    it('lets a domain failure a listener raises after the commit escape, never answering with one', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();

        $listenerFailure = AccountNotFound::withId(GoogleFixtures::GENERATED_ACCOUNT_ID);
        $this->events->shouldReceive('dispatch')->once()->andThrow($listenerFailure);

        try {
            $response = $this->useCase->handle(GoogleFixtures::input());
            $thrown = null;
        } catch (Throwable $failure) {
            $response = null;
            $thrown = $failure;
        }

        expect($thrown)->toBe($listenerFailure)
            ->and($response)->toBeNull()
            ->and($this->transactions->runs())->toBe(1);
    });

    it('stops announcing at the listener that failed, and still never answers with a failure', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();

        $announced = [];
        $this->events->shouldReceive('dispatch')->once()
            ->with(Mockery::on(function (object $event) use (&$announced): bool {
                $announced[] = $event;

                return true;
            }))
            ->andThrow(GoogleEmailNotVerified::forEmail(GoogleFixtures::EMAIL));

        try {
            $response = $this->useCase->handle(GoogleFixtures::input());
        } catch (Throwable) {
            $response = null;
        }

        expect($response)->toBeNull()
            ->and($announced)->toHaveCount(1)
            ->and($announced[0])->toBeInstanceOf(AccountRegistered::class);
    });
});
