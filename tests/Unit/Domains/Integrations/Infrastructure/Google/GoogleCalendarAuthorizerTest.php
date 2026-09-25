<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarScopeNotGranted;
use App\Domains\Integrations\Infrastructure\Google\GoogleCalendarAuthorizer;
use App\Domains\Integrations\Infrastructure\Google\GoogleOAuthClient;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\Support\FakeClock;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\GoogleOAuthDoubles;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    $this->revokeAttempts = 0;
    $this->revokeAnswer = fn () => Http::response('', 200);
    Http::fake([GoogleOAuthClient::REVOKE_URL => function () {
        $this->revokeAttempts++;

        return ($this->revokeAnswer)();
    }]);

    $this->revokedTokens = fn (): array => Http::recorded(fn (Request $request): bool => $request->url() === 'https://oauth2.googleapis.com/revoke')
        ->map(fn (array $exchange): array => $exchange[0]->data())
        ->values()
        ->all();

    $this->provider = GoogleOAuthDoubles::provider();
    $this->authorizer = new GoogleCalendarAuthorizer(
        GoogleOAuthDoubles::clientOver($this->provider),
        new FakeClock(IntegrationsFixtures::now()),
    );

    $this->grantedScopes = 'openid https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/calendar.app.created';

    $this->tokenResponse = fn (array $overrides = []): array => [
        'access_token' => IntegrationsFixtures::GRANT_ACCESS_TOKEN,
        'refresh_token' => IntegrationsFixtures::REFRESH_TOKEN,
        'expires_in' => 3599,
        'scope' => $this->grantedScopes,
        'token_type' => 'Bearer',
        ...$overrides,
    ];

    $this->googleAnswers = function (array $response, ?string $email = IntegrationsFixtures::ACCOUNT_EMAIL): void {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()->with('code-123')->andReturn($response);
        $this->provider->shouldReceive('userFromToken')
            ->with(IntegrationsFixtures::GRANT_ACCESS_TOKEN)
            ->andReturn((new User)->map(['email' => $email]));
    };

    $this->failureOf = function (): ?Throwable {
        try {
            $this->authorizer->exchange('code-123');
        } catch (Throwable $failure) {
            return $failure;
        }

        return null;
    };
});

it('builds the consent url around the state it was handed', function () {
    $this->provider->shouldReceive('with')->once()
        ->with(Mockery::on(fn (array $parameters): bool => $parameters['state'] === 'state-123'))
        ->andReturnSelf();
    $this->provider->shouldReceive('redirect')->once()
        ->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth?state=state-123'));

    expect($this->authorizer->authorizationUrl('state-123'))->toBe('https://accounts.google.com/o/oauth2/auth?state=state-123');
});

describe('a code Google exchanges with every scope granted', function () {
    beforeEach(function () {
        ($this->googleAnswers)(($this->tokenResponse)());

        $this->grant = $this->authorizer->exchange('code-123');
    });

    it('grants the account email and both tokens', function () {
        expect($this->grant)->toBeInstanceOf(CalendarGrant::class)
            ->and($this->grant->accountEmail)->toBe(IntegrationsFixtures::ACCOUNT_EMAIL)
            ->and($this->grant->tokens->accessToken)->toBe(IntegrationsFixtures::GRANT_ACCESS_TOKEN)
            ->and($this->grant->tokens->refreshToken)->toBe(IntegrationsFixtures::REFRESH_TOKEN);
    });

    it('dates the access token expiry from the clock', function () {
        expect($this->grant->tokens->accessTokenExpiresAt)->toEqual(new DateTimeImmutable('2026-03-29T10:59:59+00:00'));
    });

    it('revokes none of the tokens it accepted', function () {
        Http::assertNothingSent();
    });
});

describe('the edges of a successful exchange', function () {
    it('trims the account email', function () {
        ($this->googleAnswers)(($this->tokenResponse)(), "  ada@example.com\n");

        expect($this->authorizer->exchange('code-123')->accountEmail)->toBe('ada@example.com');
    });

    it('treats a missing lifetime as a token that expires now', function () {
        $response = ($this->tokenResponse)();
        unset($response['expires_in']);
        ($this->googleAnswers)($response);

        expect($this->authorizer->exchange('code-123')->tokens->accessTokenExpiresAt)->toEqual(IntegrationsFixtures::now());
    });

    it('never dates an expiry in the past', function () {
        ($this->googleAnswers)(($this->tokenResponse)(['expires_in' => -60]));

        expect($this->authorizer->exchange('code-123')->tokens->accessTokenExpiresAt)->toEqual(IntegrationsFixtures::now());
    });
});

describe('a consent that did not grant the calendar scope', function () {
    it('refuses the exchange', function (mixed $scope) {
        ($this->googleAnswers)(($this->tokenResponse)(['scope' => $scope]));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarScopeNotGranted::class)
            ->and($failure)->toBeInstanceOf(DomainFailure::class)
            ->and($failure->errorCode())->toBe('calendar_scope_not_granted')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    })->with([
        'identity only' => ['openid https://www.googleapis.com/auth/userinfo.email'],
        'no scope at all' => [''],
        'a scope that is not text' => [['https://www.googleapis.com/auth/calendar.app.created']],
        'the scope as a prefix of another' => ['https://www.googleapis.com/auth/calendar.app.created.readonly'],
    ]);

    it('refuses before asking Google who the account is', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()->andReturn(($this->tokenResponse)(['scope' => 'openid']));
        $this->provider->shouldNotReceive('userFromToken');

        expect(($this->failureOf)())->toBeInstanceOf(CalendarScopeNotGranted::class);
    });
});

describe('an exchange that cannot produce a usable grant', function () {
    it('refuses the authorization when the code cannot be exchanged', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()
            ->andThrow(new RuntimeException('invalid_grant for code code-123'));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->errorCode())->toBe('calendar_authorization_failed')
            ->and($failure->kind())->toBe(DomainFailureKind::Invalid);
    });

    it('never carries the code in the refusal or its chain', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()
            ->andThrow(new RuntimeException('invalid_grant for code code-123'));

        $failure = ($this->failureOf)();

        expect($failure->getMessage())->not->toContain('code-123')
            ->and($failure->getPrevious())->toBeNull();
    });

    it('refuses the authorization when Google granted no access token', function (mixed $accessToken) {
        ($this->googleAnswers)(($this->tokenResponse)(['access_token' => $accessToken]));

        expect(($this->failureOf)())->toBeInstanceOf(CalendarAuthorizationFailed::class);
    })->with(['empty' => [''], 'missing' => [null], 'not text' => [42]]);

    it('refuses the authorization when Google granted no refresh token', function (mixed $refreshToken) {
        ($this->googleAnswers)(($this->tokenResponse)(['refresh_token' => $refreshToken]));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->toContain('refresh token');
    })->with(['empty' => [''], 'missing' => [null]]);

    it('refuses the authorization when Google discloses no account email', function (?string $email) {
        ($this->googleAnswers)(($this->tokenResponse)(), $email);

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->toContain('account email');
    })->with(['missing' => [null], 'empty' => [''], 'whitespace' => ['   ']]);

    it('refuses the authorization when the account cannot be read, without leaking the token', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()->andReturn(($this->tokenResponse)());
        $this->provider->shouldReceive('userFromToken')->once()
            ->andThrow(new RuntimeException('401 for Bearer '.IntegrationsFixtures::GRANT_ACCESS_TOKEN));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::GRANT_ACCESS_TOKEN)
            ->and($failure->getPrevious())->toBeNull();
    });
});

describe('a refusal after Google issued tokens', function () {
    it('revokes the refresh token when the calendar scope was not granted', function () {
        ($this->googleAnswers)(($this->tokenResponse)(['scope' => 'openid']));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarScopeNotGranted::class)
            ->and($failure->getMessage())->toBe(CalendarScopeNotGranted::forScope(GoogleOAuthClient::CALENDAR_SCOPE)->getMessage())
            ->and(($this->revokedTokens)())->toBe([['token' => IntegrationsFixtures::REFRESH_TOKEN]]);
    });

    it('revokes the refresh token when Google granted no access token', function (mixed $accessToken) {
        ($this->googleAnswers)(($this->tokenResponse)(['access_token' => $accessToken]));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->toBe(CalendarAuthorizationFailed::exchangeFailed()->getMessage())
            ->and(($this->revokedTokens)())->toBe([['token' => IntegrationsFixtures::REFRESH_TOKEN]]);
    })->with(['empty' => [''], 'missing' => [null], 'not text' => [42]]);

    it('revokes the access token when Google granted no refresh token', function (mixed $refreshToken) {
        ($this->googleAnswers)(($this->tokenResponse)(['refresh_token' => $refreshToken]));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->toBe(CalendarAuthorizationFailed::missingRefreshToken()->getMessage())
            ->and(($this->revokedTokens)())->toBe([['token' => IntegrationsFixtures::GRANT_ACCESS_TOKEN]]);
    })->with(['empty' => [''], 'missing' => [null], 'not text' => [42]]);

    it('revokes the refresh token when Google discloses no account email', function (?string $email) {
        ($this->googleAnswers)(($this->tokenResponse)(), $email);

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->toBe(CalendarAuthorizationFailed::missingAccountEmail()->getMessage())
            ->and(($this->revokedTokens)())->toBe([['token' => IntegrationsFixtures::REFRESH_TOKEN]]);
    })->with(['missing' => [null], 'empty' => [''], 'whitespace' => ['   ']]);

    it('revokes the refresh token when the account cannot be read', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()->andReturn(($this->tokenResponse)());
        $this->provider->shouldReceive('userFromToken')->once()->andThrow(new RuntimeException('401 Unauthorized'));

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarAuthorizationFailed::class)
            ->and($failure->getMessage())->toBe(CalendarAuthorizationFailed::missingAccountEmail()->getMessage())
            ->and(($this->revokedTokens)())->toBe([['token' => IntegrationsFixtures::REFRESH_TOKEN]]);
    });

    it('revokes nothing when Google issued neither token', function (array $overrides, string $refusal) {
        ($this->googleAnswers)(($this->tokenResponse)($overrides));

        expect(($this->failureOf)())->toBeInstanceOf($refusal);

        Http::assertNothingSent();
    })->with([
        'scope refused, both empty' => [['scope' => 'openid', 'access_token' => '', 'refresh_token' => ''], CalendarScopeNotGranted::class],
        'scope refused, both missing' => [['scope' => 'openid', 'access_token' => null, 'refresh_token' => null], CalendarScopeNotGranted::class],
        'scope granted, both empty' => [['access_token' => '', 'refresh_token' => ''], CalendarAuthorizationFailed::class],
        'scope granted, neither text' => [['access_token' => 42, 'refresh_token' => ['x']], CalendarAuthorizationFailed::class],
    ]);

    it('revokes nothing when the code itself could not be exchanged', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()->andThrow(new RuntimeException('invalid_grant'));

        expect(($this->failureOf)())->toBeInstanceOf(CalendarAuthorizationFailed::class);

        Http::assertNothingSent();
    });
});

describe('a revocation Google does not complete', function () {
    beforeEach(function () {
        ($this->googleAnswers)(($this->tokenResponse)(['scope' => 'openid']));
    });

    it('still refuses with the original failure', function (Closure $answer) {
        $this->revokeAnswer = $answer;

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(CalendarScopeNotGranted::class)
            ->and($failure->getMessage())->toBe(CalendarScopeNotGranted::forScope(GoogleOAuthClient::CALENDAR_SCOPE)->getMessage())
            ->and($failure->getPrevious())->toBeNull();
    })->with('unfinished revocations');

    it('never carries a token in the refusal', function (Closure $answer) {
        $this->revokeAnswer = $answer;

        $failure = ($this->failureOf)();

        expect($failure->getMessage())->not->toContain(IntegrationsFixtures::REFRESH_TOKEN)
            ->and($failure->getMessage())->not->toContain(IntegrationsFixtures::GRANT_ACCESS_TOKEN);
    })->with('unfinished revocations');

    it('attempts the revocation exactly once', function (Closure $answer) {
        $this->revokeAnswer = $answer;

        ($this->failureOf)();

        expect($this->revokeAttempts)->toBe(1);
    })->with('unfinished revocations');
});

dataset('unfinished revocations', [
    'a server error' => [fn () => fn () => Http::response('', 500)],
    'unavailable' => [fn () => fn () => Http::response('', 503)],
    'unauthorized' => [fn () => fn () => Http::response('', 401)],
    'a dropped connection' => [fn () => Http::failedConnection('cURL error 28 for token='.IntegrationsFixtures::REFRESH_TOKEN)],
    'an unexpected throwable' => [fn () => fn () => throw new LogicException('boom for token='.IntegrationsFixtures::REFRESH_TOKEN)],
]);
