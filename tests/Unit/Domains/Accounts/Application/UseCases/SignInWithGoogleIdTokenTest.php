<?php

declare(strict_types=1);

use App\Domains\Accounts\Application\Dtos\AuthenticatedAccountData;
use App\Domains\Accounts\Application\Dtos\SignInWithGoogleIdTokenInput;
use App\Domains\Accounts\Application\UseCases\AuthenticateWithGoogle;
use App\Domains\Accounts\Application\UseCases\SignInWithGoogleIdToken;
use App\Domains\Accounts\Contracts\AccountRepository;
use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Domains\Accounts\Contracts\SocialIdentityRepository;
use App\Domains\Accounts\Entities\Account;
use App\Domains\Accounts\Entities\SocialIdentity;
use App\Domains\Accounts\Exceptions\GoogleEmailNotVerified;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Accounts\GoogleFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->verifier = Mockery::mock(GoogleIdentityVerifier::class);
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->socialIdentities = Mockery::mock(SocialIdentityRepository::class);
    $this->events = Mockery::mock(Dispatcher::class);

    $this->useCase = new SignInWithGoogleIdToken(
        $this->verifier,
        new AuthenticateWithGoogle(
            $this->accounts,
            $this->socialIdentities,
            new FixedIdGenerator(GoogleFixtures::GENERATED_ACCOUNT_ID, GoogleFixtures::GENERATED_IDENTITY_ID),
            new FakeClock(GoogleFixtures::now()),
            new FakeTransactionManager,
            $this->events,
        ),
    );
});

it('signs in the account behind a verified token', function () {
    $this->verifier->shouldReceive('verify')->once()->with('a.valid.token')
        ->andReturn(GoogleFixtures::identity());
    $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
        ->with(SocialProvider::Google, GoogleFixtures::SUB)
        ->andReturn(GoogleFixtures::storedIdentity());
    $this->accounts->shouldReceive('findById')->once()
        ->with(GoogleFixtures::EXISTING_ACCOUNT_ID)
        ->andReturn(GoogleFixtures::storedAccount(emailVerifiedAt: GoogleFixtures::now()));

    $data = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

    expect($data)->toBeInstanceOf(AuthenticatedAccountData::class)
        ->and($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
        ->and($data->name)->toBe('Ada Lovelace')
        ->and($data->email)->toBe('ada@example.com')
        ->and($data->isNewAccount)->toBeFalse();
});

it('registers a first-time visitor from the identity the token asserts', function () {
    $this->verifier->shouldReceive('verify')->once()
        ->andReturn(GoogleFixtures::identity(
            sub: 'google-subject-9000',
            email: 'Grace@Example.com',
            name: '  Grace Hopper  ',
            avatarUrl: 'https://lh3.googleusercontent.com/a/grace',
        ));
    $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
        ->with(SocialProvider::Google, 'google-subject-9000')
        ->andReturn(null);
    $this->accounts->shouldReceive('findByEmail')->once()->with('grace@example.com')->andReturn(null);

    $savedAccount = null;
    $savedIdentity = null;
    $this->accounts->shouldReceive('save')->once()->with(Mockery::capture($savedAccount));
    $this->socialIdentities->shouldReceive('save')->once()->with(Mockery::capture($savedIdentity));
    $this->events->shouldReceive('dispatch')->twice();

    $data = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

    expect($data->id)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
        ->and($data->name)->toBe('Grace Hopper')
        ->and($data->email)->toBe('grace@example.com')
        ->and($data->isNewAccount)->toBeTrue();

    expect($savedAccount)->toBeInstanceOf(Account::class)
        ->and($savedAccount->name())->toBe('Grace Hopper')
        ->and($savedAccount->email())->toBe('grace@example.com');

    expect($savedIdentity)->toBeInstanceOf(SocialIdentity::class)
        ->and($savedIdentity->providerUserId)->toBe('google-subject-9000')
        ->and($savedIdentity->accountId)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID);
});

it('carries the unverified flag through to the takeover guard', function () {
    $this->verifier->shouldReceive('verify')->once()
        ->andReturn(GoogleFixtures::identity(emailVerified: false));
    $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);

    $this->accounts->shouldNotReceive('save');
    $this->socialIdentities->shouldNotReceive('save');
    $this->events->shouldNotReceive('dispatch');

    expect(fn () => $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token')))
        ->toThrow(GoogleEmailNotVerified::class, 'Google has not verified the email address [ada@example.com].');
});

it('propagates an unverifiable token and never authenticates on it', function () {
    $this->verifier->shouldReceive('verify')->once()->with('forged.or.expired')
        ->andThrow(InvalidGoogleIdToken::unverifiable(new RuntimeException('bad signature')));

    $this->socialIdentities->shouldNotReceive('findByProviderUserId');
    $this->accounts->shouldNotReceive('findById');
    $this->accounts->shouldNotReceive('findByEmail');
    $this->accounts->shouldNotReceive('save');
    $this->socialIdentities->shouldNotReceive('save');
    $this->events->shouldNotReceive('dispatch');

    expect(fn () => $this->useCase->handle(new SignInWithGoogleIdTokenInput('forged.or.expired')))
        ->toThrow(InvalidGoogleIdToken::class, 'The Google ID token could not be verified.');
});

it('propagates every way a credential can fail verification', function (InvalidGoogleIdToken $failure) {
    $this->verifier->shouldReceive('verify')->once()->andThrow($failure);

    $this->socialIdentities->shouldNotReceive('findByProviderUserId');
    $this->events->shouldNotReceive('dispatch');

    expect(fn () => $this->useCase->handle(new SignInWithGoogleIdTokenInput('not-a-jwt')))
        ->toThrow(InvalidGoogleIdToken::class, $failure->getMessage());
})->with([
    'not a jwt' => InvalidGoogleIdToken::notAJsonWebToken(),
    'no subject claim' => InvalidGoogleIdToken::missingSubject(),
]);

it('hands the verifier exactly the token it was given, unaltered', function (string $idToken) {
    $this->verifier->shouldReceive('verify')->once()->with($idToken)
        ->andThrow(InvalidGoogleIdToken::notAJsonWebToken());

    expect(fn () => $this->useCase->handle(new SignInWithGoogleIdTokenInput($idToken)))
        ->toThrow(InvalidGoogleIdToken::class);
})->with([
    'padded' => '  a.valid.token  ',
    'mixed case' => 'A.Valid.Token',
    'not shaped like a jwt at all' => 'nonsense',
]);

it('refuses a blank credential before the verifier is ever consulted', function (string $idToken) {
    $this->verifier->shouldNotReceive('verify');
    $this->socialIdentities->shouldNotReceive('findByProviderUserId');
    $this->accounts->shouldNotReceive('findByEmail');
    $this->accounts->shouldNotReceive('save');
    $this->events->shouldNotReceive('dispatch');

    expect(fn () => $this->useCase->handle(new SignInWithGoogleIdTokenInput($idToken)))
        ->toThrow(InvalidGoogleIdToken::class, 'The supplied credential is not a Google ID token.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'tab' => "\t",
]);
