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
use App\Domains\Accounts\Exceptions\InvalidAccountEmail;
use App\Domains\Accounts\Exceptions\InvalidAccountName;
use App\Domains\Accounts\Exceptions\SocialIdentityAlreadyLinked;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Accounts\GoogleFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

function mizitaCatch(callable $work): ?Throwable
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
    $this->socialIdentities = Mockery::mock(SocialIdentityRepository::class);
    $this->events = Mockery::mock(Dispatcher::class);
    $this->transactions = new FakeTransactionManager;

    $this->useCase = new AuthenticateWithGoogle(
        $this->accounts,
        $this->socialIdentities,
        new FixedIdGenerator(GoogleFixtures::GENERATED_ACCOUNT_ID, GoogleFixtures::GENERATED_IDENTITY_ID),
        new FakeClock(GoogleFixtures::now()),
        $this->transactions,
        $this->events,
    );

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

        $data = $this->useCase->handle(GoogleFixtures::input());

        expect($data)->toBeInstanceOf(AuthenticatedAccountData::class)
            ->and($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeFalse();
    });

    it('opens no transaction at all', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->andReturn(GoogleFixtures::storedAccount());

        $this->useCase->handle(GoogleFixtures::input());

        expect($this->transactions->runs())->toBe(0);
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

        $data = $this->useCase->handle(GoogleFixtures::input(email: 'ada.new@example.com'));

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

        expect($this->useCase->handle(GoogleFixtures::input(emailVerified: false))->id)
            ->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID);
    });

    it('propagates AccountNotFound when the link points at an account that is gone', function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')
            ->andReturn(GoogleFixtures::storedIdentity('vanished-account-uuid'));
        $this->accounts->shouldReceive('findById')->once()
            ->with('vanished-account-uuid')
            ->andThrow(AccountNotFound::withId('vanished-account-uuid'));

        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input()))
            ->toThrow(AccountNotFound::class, 'Account [vanished-account-uuid] was not found.');
    });
});

describe('the account takeover guard', function () {
    beforeEach(function () {
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
    });

    it('refuses an unverified Google email, naming the address', function () {
        expect(fn () => $this->useCase->handle(GoogleFixtures::input(emailVerified: false)))
            ->toThrow(GoogleEmailNotVerified::class, 'Google has not verified the email address [ada@example.com].');
    });

    it('writes nothing when it refuses', function () {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input(emailVerified: false)))
            ->toThrow(GoogleEmailNotVerified::class);
    });

    it('announces nothing when it refuses', function () {
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input(emailVerified: false)))
            ->toThrow(GoogleEmailNotVerified::class);
    });

    it('never looks the address up, so an existing account is not even probed', function () {
        $this->accounts->shouldNotReceive('findByEmail');
        $this->accounts->shouldNotReceive('findById');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input(emailVerified: false)))
            ->toThrow(GoogleEmailNotVerified::class);
    });

    it('refuses before opening a transaction', function () {
        expect(fn () => $this->useCase->handle(GoogleFixtures::input(emailVerified: false)))
            ->toThrow(GoogleEmailNotVerified::class);

        expect($this->transactions->runs())->toBe(0);
    });

    it('refuses whatever the address looks like', function (string $email) {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input(email: $email, emailVerified: false)))
            ->toThrow(GoogleEmailNotVerified::class);
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

        $data = $this->useCase->handle(GoogleFixtures::input());

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

    it('matches the address case insensitively', function (string $googleEmail) {
        $this->accounts->shouldReceive('findByEmail')->once()->with('ada@example.com')
            ->andReturn(GoogleFixtures::storedAccount());
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->once();

        expect($this->useCase->handle(GoogleFixtures::input(email: $googleEmail))->isNewAccount)->toBeFalse();
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

        $data = $this->useCase->handle(GoogleFixtures::input(email: 'Ada@Example.com', name: '  Ada Lovelace  '));

        expect($data)->toBeInstanceOf(AuthenticatedAccountData::class)
            ->and($data->id)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeTrue();

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

    it('registers inside a single transaction', function () {
        $this->accounts->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->socialIdentities->shouldReceive('save')->once()->with(Mockery::on($this->recordTransactionState));
        $this->events->shouldReceive('dispatch')->twice();

        $this->useCase->handle(GoogleFixtures::input());

        expect($this->transactions->runs())->toBe(1)
            ->and($this->insideTransaction)->toBe([true, true]);
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

        expect(fn () => $this->useCase->handle(GoogleFixtures::input(name: '   ')))
            ->toThrow(InvalidAccountName::class, 'An account name cannot be empty.');
    });

    it('refuses to register a malformed address, writing and announcing nothing', function () {
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(GoogleFixtures::input(email: 'ada.example.com')))
            ->toThrow(InvalidAccountEmail::class, '[ada.example.com] is not a valid email address.');
    });

    it('keeps accents in the name it registers', function () {
        $savedAccount = null;
        $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($savedAccount));
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->twice();

        $data = $this->useCase->handle(GoogleFixtures::input(name: 'José Álvarez Muñoz'));

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

        $data = $this->useCase->handle(GoogleFixtures::input());

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
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

        $data = $this->useCase->handle(GoogleFixtures::input());

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
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

    it('rethrows the address conflict untouched when the rows are still not there', function () {
        $conflict = AccountAlreadyRegistered::withEmail('ada@example.com');

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->with('ada@example.com')->andReturn(null, null);
        $this->accounts->shouldReceive('save')->once()->andThrow($conflict);

        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');

        expect(mizitaCatch(fn () => $this->useCase->handle(GoogleFixtures::input())))->toBe($conflict);
    });

    it('rethrows the link conflict untouched when the rows are still not there', function () {
        $conflict = SocialIdentityAlreadyLinked::forProviderUser(SocialProvider::Google, GoogleFixtures::SUB);

        $this->socialIdentities->shouldReceive('findByProviderUserId')->twice()->andReturn(null, null);
        $this->accounts->shouldReceive('findByEmail')->twice()->with('ada@example.com')->andReturn(null, null);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once()->andThrow($conflict);

        $this->events->shouldNotReceive('dispatch');

        expect(mizitaCatch(fn () => $this->useCase->handle(GoogleFixtures::input())))->toBe($conflict);
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

        $data = $this->useCase->handle(GoogleFixtures::input());

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->isNewAccount)->toBeFalse();
    });
});
