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
use App\Domains\Accounts\Exceptions\AccountNotFound;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\Exceptions\TwoFactorRequired;
use App\Domains\Accounts\ValueObjects\SecondFactorProofKind;
use App\Domains\Accounts\ValueObjects\SocialProvider;
use App\Domains\Accounts\ValueObjects\TwoFactorStatus;
use App\Shared\Application\UseCaseResponse;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Contracts\Events\Dispatcher;
use Tests\Support\Accounts\FakeSecondFactorVerifier;
use Tests\Support\Accounts\GoogleFixtures;
use Tests\Support\FakeClock;
use Tests\Support\FakeTransactionManager;
use Tests\Support\FixedIdGenerator;

beforeEach(function () {
    $this->verifier = Mockery::mock(GoogleIdentityVerifier::class);
    $this->accounts = Mockery::mock(AccountRepository::class);
    $this->socialIdentities = Mockery::mock(SocialIdentityRepository::class);
    $this->events = Mockery::mock(Dispatcher::class);
    $this->secondFactors = new FakeSecondFactorVerifier;

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
        $this->secondFactors,
    );

    $this->accountWithTwoFactor = fn (TwoFactorStatus $status): Account => Account::restore(
        GoogleFixtures::EXISTING_ACCOUNT_ID,
        GoogleFixtures::NAME,
        GoogleFixtures::EMAIL,
        new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
        twoFactorStatus: $status,
    );

    $this->signingInAs = function (Account $account): void {
        $this->verifier->shouldReceive('verify')->once()->andReturn(GoogleFixtures::identity());
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
            ->with(SocialProvider::Google, GoogleFixtures::SUB)
            ->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()
            ->with(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->andReturn($account);
        $this->accounts->shouldNotReceive('save');
        $this->socialIdentities->shouldNotReceive('save');
        $this->events->shouldNotReceive('dispatch');
    };
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

    $data = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'))->value();

    expect($data)->toBeInstanceOf(AuthenticatedAccountData::class)
        ->and($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
        ->and($data->name)->toBe('Ada Lovelace')
        ->and($data->email)->toBe('ada@example.com')
        ->and($data->isNewAccount)->toBeFalse()
        ->and($data->requiresSecondFactor)->toBeFalse();
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

    $data = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'))->value();

    expect($data->id)->toBe(GoogleFixtures::GENERATED_ACCOUNT_ID)
        ->and($data->name)->toBe('Grace Hopper')
        ->and($data->email)->toBe('grace@example.com')
        ->and($data->isNewAccount)->toBeTrue()
        ->and($data->requiresSecondFactor)->toBeFalse();

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

    $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('google_email_not_verified')
        ->and($response->error()->kind)->toBe(DomainFailureKind::Forbidden)
        ->and($response->error()->cause()->getMessage())
        ->toBe('Google has not verified the email address [ada@example.com].');
});

it('refuses an unverifiable token and never authenticates on it', function () {
    $this->verifier->shouldReceive('verify')->once()->with('forged.or.expired')
        ->andThrow(InvalidGoogleIdToken::unverifiable(new RuntimeException('bad signature')));

    $this->socialIdentities->shouldNotReceive('findByProviderUserId');
    $this->accounts->shouldNotReceive('findById');
    $this->accounts->shouldNotReceive('findByEmail');
    $this->accounts->shouldNotReceive('save');
    $this->socialIdentities->shouldNotReceive('save');
    $this->events->shouldNotReceive('dispatch');

    $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('forged.or.expired'));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('google_invalid_id_token')
        ->and($response->error()->kind)->toBe(DomainFailureKind::Unauthenticated)
        ->and($response->error()->cause()->getMessage())->toBe('The Google ID token could not be verified.');
});

it('refuses every way a credential can fail verification', function (InvalidGoogleIdToken $failure) {
    $this->verifier->shouldReceive('verify')->once()->andThrow($failure);

    $this->socialIdentities->shouldNotReceive('findByProviderUserId');
    $this->events->shouldNotReceive('dispatch');

    $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('not-a-jwt'));

    expect($response->error()->code)->toBe('google_invalid_id_token')
        ->and($response->error()->cause())->toBe($failure);
})->with([
    'not a jwt' => InvalidGoogleIdToken::notAJsonWebToken(),
    'no subject claim' => InvalidGoogleIdToken::missingSubject(),
]);

it('hands the verifier exactly the token it was given, unaltered', function (string $idToken) {
    $this->verifier->shouldReceive('verify')->once()->with($idToken)
        ->andThrow(InvalidGoogleIdToken::notAJsonWebToken());

    expect($this->useCase->handle(new SignInWithGoogleIdTokenInput($idToken))->error()->code)
        ->toBe('google_invalid_id_token');
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

    $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput($idToken));

    expect($response->failed())->toBeTrue()
        ->and($response->error()->code)->toBe('google_invalid_id_token')
        ->and($response->error()->cause()->getMessage())
        ->toBe('The supplied credential is not a Google ID token.');
})->with([
    'empty' => '',
    'spaces' => '   ',
    'tab' => "\t",
]);

describe('the response it hands back', function () {
    it('reports success with no warning once the token has been honoured', function () {
        $this->verifier->shouldReceive('verify')->once()->andReturn(GoogleFixtures::identity());
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
            ->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()->andReturn(GoogleFixtures::storedAccount());

        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

        expect($response)->toBeInstanceOf(UseCaseResponse::class)
            ->and($response->succeeded())->toBeTrue()
            ->and($response->warnings())->toBe([]);
    });

    it('returns the refusal the authentication produced, rather than wrapping it in one of its own', function () {
        $missing = AccountNotFound::withId('vanished-account-uuid');

        $this->verifier->shouldReceive('verify')->once()->andReturn(GoogleFixtures::identity());
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
            ->andReturn(GoogleFixtures::storedIdentity('vanished-account-uuid'));
        $this->accounts->shouldReceive('findById')->once()->andThrow($missing);

        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('account_not_found')
            ->and($response->error()->code)->not->toBe('google_invalid_id_token')
            ->and($response->error()->kind)->toBe(DomainFailureKind::NotFound)
            ->and($response->error()->cause())->toBe($missing);
    });

    it('passes a successful authentication straight back, value and all', function () {
        $account = GoogleFixtures::storedAccount(emailVerifiedAt: GoogleFixtures::now());

        $this->verifier->shouldReceive('verify')->once()->andReturn(GoogleFixtures::identity());
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()
            ->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()->andReturn($account);

        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

        expect($response->value())->toEqual(AuthenticatedAccountData::forExistingAccount($account));
    });

    it('lets a verifier failure that is not a domain failure escape', function () {
        $this->verifier->shouldReceive('verify')->once()
            ->andThrow(new RuntimeException('the Google certificate endpoint is unreachable'));

        $this->socialIdentities->shouldNotReceive('findByProviderUserId');
        $this->events->shouldNotReceive('dispatch');

        expect(fn () => $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token')))
            ->toThrow(RuntimeException::class, 'the Google certificate endpoint is unreachable');
    });
});

describe('an account that has not turned two factor authentication on', function () {
    it('signs in on the token alone, never consulting the second factor', function (TwoFactorStatus $status) {
        ($this->signingInAs)(($this->accountWithTwoFactor)($status));

        $data = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'))->value();

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->requiresSecondFactor)->toBeFalse()
            ->and($this->secondFactors->verifications)->toBe([]);
    })->with([
        'disabled' => TwoFactorStatus::Disabled,
        'set up but never confirmed' => TwoFactorStatus::Pending,
    ]);

    it('ignores a code it did not ask for', function (SignInWithGoogleIdTokenInput $input) {
        ($this->signingInAs)(($this->accountWithTwoFactor)(TwoFactorStatus::Disabled));

        expect($this->useCase->handle($input)->succeeded())->toBeTrue()
            ->and($this->secondFactors->verifications)->toBe([]);
    })->with([
        'a wrong code' => fn () => new SignInWithGoogleIdTokenInput('a.valid.token', code: '000000'),
        'a wrong recovery code' => fn () => new SignInWithGoogleIdTokenInput('a.valid.token', recoveryCode: 'not-a-recovery-code'),
    ]);

    it('never consults the second factor for an account it has just registered', function () {
        $this->verifier->shouldReceive('verify')->once()->andReturn(GoogleFixtures::identity());
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(null);
        $this->accounts->shouldReceive('findByEmail')->once()->andReturn(null);
        $this->accounts->shouldReceive('save')->once();
        $this->socialIdentities->shouldReceive('save')->once();
        $this->events->shouldReceive('dispatch')->twice();

        $data = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token', code: '000000'))->value();

        expect($data->isNewAccount)->toBeTrue()
            ->and($data->requiresSecondFactor)->toBeFalse()
            ->and($this->secondFactors->verifications)->toBe([]);
    });
});

describe('an account with two factor authentication enabled', function () {
    beforeEach(function () {
        ($this->signingInAs)(($this->accountWithTwoFactor)(TwoFactorStatus::Enabled));
    });

    it('answers two_factor_required as unauthenticated when no second factor was given', function () {
        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('two_factor_required')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Unauthenticated)
            ->and($response->error()->cause())->toBeInstanceOf(TwoFactorRequired::class)
            ->and($response->error()->cause()->getMessage())
            ->toBe('Account [existing-account-uuid] requires a second factor to sign in.')
            ->and($this->secondFactors->verifications)->toBe([]);
    });

    it('withholds the account behind the challenge, so no token can be issued for it', function () {
        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token'));

        expect($response->succeeded())->toBeFalse()
            ->and(fn () => $response->value())->toThrow(TwoFactorRequired::class);
    });

    it('reads blank codes in the payload as no second factor at all', function () {
        $response = $this->useCase->handle(SignInWithGoogleIdTokenInput::fromRequest([
            'id_token' => 'a.valid.token',
            'code' => '   ',
            'recovery_code' => '',
        ]));

        expect($response->error()->code)->toBe('two_factor_required')
            ->and($this->secondFactors->verifications)->toBe([]);
    });

    it('signs in with a valid authenticator code', function () {
        $data = $this->useCase->handle(
            new SignInWithGoogleIdTokenInput('a.valid.token', code: FakeSecondFactorVerifier::VALID_CODE),
        )->value();

        expect($data)->toBeInstanceOf(AuthenticatedAccountData::class)
            ->and($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->name)->toBe('Ada Lovelace')
            ->and($data->email)->toBe('ada@example.com')
            ->and($data->isNewAccount)->toBeFalse()
            ->and($data->requiresSecondFactor)->toBeTrue();
    });

    it('checks the authenticator code against the account the token resolved to, exactly once', function () {
        $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token', code: FakeSecondFactorVerifier::VALID_CODE));

        expect($this->secondFactors->verifications)->toHaveCount(1)
            ->and($this->secondFactors->verifications[0]['accountId'])->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($this->secondFactors->verifications[0]['proof']->kind())->toBe(SecondFactorProofKind::Totp)
            ->and($this->secondFactors->verifications[0]['proof']->value())->toBe(FakeSecondFactorVerifier::VALID_CODE);
    });

    it('signs in with a valid recovery code', function () {
        $data = $this->useCase->handle(
            new SignInWithGoogleIdTokenInput('a.valid.token', recoveryCode: FakeSecondFactorVerifier::VALID_RECOVERY_CODE),
        )->value();

        expect($data->id)->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($data->requiresSecondFactor)->toBeTrue();
    });

    it('checks the recovery code as a recovery code, never as an authenticator code', function () {
        $this->useCase->handle(
            new SignInWithGoogleIdTokenInput('a.valid.token', recoveryCode: FakeSecondFactorVerifier::VALID_RECOVERY_CODE),
        );

        expect($this->secondFactors->verifications)->toHaveCount(1)
            ->and($this->secondFactors->verifications[0]['accountId'])->toBe(GoogleFixtures::EXISTING_ACCOUNT_ID)
            ->and($this->secondFactors->verifications[0]['proof']->kind())->toBe(SecondFactorProofKind::RecoveryCode)
            ->and($this->secondFactors->verifications[0]['proof']->value())->toBe(FakeSecondFactorVerifier::VALID_RECOVERY_CODE);
    });

    it('hands the verifier the code trimmed from the payload', function () {
        $response = $this->useCase->handle(SignInWithGoogleIdTokenInput::fromRequest([
            'id_token' => 'a.valid.token',
            'code' => '  '.FakeSecondFactorVerifier::VALID_CODE."\n",
        ]));

        expect($response->succeeded())->toBeTrue()
            ->and($this->secondFactors->verifications[0]['proof']->value())->toBe(FakeSecondFactorVerifier::VALID_CODE);
    });

    it('refuses a wrong authenticator code with invalid_two_factor_code', function () {
        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token', code: '000000'));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_two_factor_code')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause()->getMessage())
            ->toBe('The two factor code given for account [existing-account-uuid] is not valid.');
    });

    it('refuses a wrong recovery code with invalid_recovery_code', function () {
        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token', recoveryCode: 'WRONG-RECOVERY'));

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe('invalid_recovery_code')
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause()->getMessage())
            ->toBe('The recovery code given for account [existing-account-uuid] is not valid.');
    });

    it('withholds the account behind a rejected second factor', function (SignInWithGoogleIdTokenInput $input) {
        $response = $this->useCase->handle($input);

        expect(fn () => $response->value())->toThrow(DomainException::class);
    })->with([
        'a wrong code' => fn () => new SignInWithGoogleIdTokenInput('a.valid.token', code: '000000'),
        'a wrong recovery code' => fn () => new SignInWithGoogleIdTokenInput('a.valid.token', recoveryCode: 'WRONG-RECOVERY'),
    ]);

    it('lets a verifier breakdown that is not a domain failure escape', function () {
        $this->secondFactors->breakWith(new RuntimeException('the encryption key cannot decrypt the secret'));

        expect(fn () => $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token', code: '123456')))
            ->toThrow(RuntimeException::class, 'the encryption key cannot decrypt the secret');
    });
});

describe('a refused authentication', function () {
    it('never reaches the second factor', function () {
        $this->verifier->shouldReceive('verify')->once()->andReturn(GoogleFixtures::identity());
        $this->socialIdentities->shouldReceive('findByProviderUserId')->once()->andReturn(GoogleFixtures::storedIdentity());
        $this->accounts->shouldReceive('findById')->once()->andReturn(Account::restore(
            GoogleFixtures::EXISTING_ACCOUNT_ID,
            GoogleFixtures::NAME,
            GoogleFixtures::EMAIL,
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            new DateTimeImmutable('2025-05-01T08:30:00+00:00'),
            deletionRequestedAt: new DateTimeImmutable('2025-12-20T09:15:00+00:00'),
            twoFactorStatus: TwoFactorStatus::Enabled,
        ));

        $response = $this->useCase->handle(new SignInWithGoogleIdTokenInput('a.valid.token', code: FakeSecondFactorVerifier::VALID_CODE));

        expect($response->error()->code)->toBe('account_pending_reactivation')
            ->and($this->secondFactors->verifications)->toBe([]);
    });
});

describe('a payload whose second factor is malformed', function () {
    it('refuses it before the token is verified or the second factor consulted', function (SignInWithGoogleIdTokenInput $input, string $code, string $message) {
        $this->verifier->shouldNotReceive('verify');
        $this->socialIdentities->shouldNotReceive('findByProviderUserId');
        $this->accounts->shouldNotReceive('findById');
        $this->events->shouldNotReceive('dispatch');

        $response = $this->useCase->handle($input);

        expect($response->failed())->toBeTrue()
            ->and($response->error()->code)->toBe($code)
            ->and($response->error()->kind)->toBe(DomainFailureKind::Invalid)
            ->and($response->error()->cause()->getMessage())->toBe($message)
            ->and($this->secondFactors->verifications)->toBe([]);
    })->with([
        'both proofs at once' => [
            fn () => new SignInWithGoogleIdTokenInput('a.valid.token', '123456', 'ABCDE12345-FGHIJ67890'),
            'invalid_two_factor_code',
            'A two factor code and a recovery code cannot be given together.',
        ],
        'a code past its limit' => [
            fn () => new SignInWithGoogleIdTokenInput('a.valid.token', code: str_repeat('1', SignInWithGoogleIdTokenInput::MAXIMUM_CODE_LENGTH + 1)),
            'invalid_two_factor_code',
            'The two factor code exceeds 16 characters.',
        ],
        'a recovery code past its limit' => [
            fn () => new SignInWithGoogleIdTokenInput('a.valid.token', recoveryCode: str_repeat('a', SignInWithGoogleIdTokenInput::MAXIMUM_RECOVERY_CODE_LENGTH + 1)),
            'invalid_recovery_code',
            'The recovery code exceeds 64 characters.',
        ],
    ]);
});
