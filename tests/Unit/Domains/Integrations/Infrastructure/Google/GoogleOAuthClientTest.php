<?php

declare(strict_types=1);

use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Domains\Integrations\Infrastructure\Google\GoogleOAuthClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\Token;
use Laravel\Socialite\Two\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\GoogleOAuthDoubles;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;

uses(TestCase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    $this->provider = GoogleOAuthDoubles::provider();
    $this->socialite = GoogleOAuthDoubles::socialiteBuilding($this->provider);
    $this->client = GoogleOAuthDoubles::clientBuiltBy($this->socialite);
});

describe('the provider it builds', function () {
    it('builds a Google provider from the calendar credentials, with the write timeout', function () {
        $this->socialite->shouldReceive('buildProvider')->once()
            ->with(GoogleProvider::class, [
                'client_id' => GoogleOAuthDoubles::CLIENT_ID,
                'client_secret' => GoogleOAuthDoubles::CLIENT_SECRET,
                'redirect' => GoogleOAuthDoubles::REDIRECT_URI,
                'guzzle' => ['timeout' => GoogleOAuthDoubles::TIMEOUT_SECONDS],
            ])
            ->andReturn($this->provider);
        $this->provider->shouldReceive('refreshToken')->andReturn(new Token('t', 'r', 1, []));

        $this->client->refresh(IntegrationsFixtures::REFRESH_TOKEN);
    });

    it('keeps no session state and asks for identity plus the app created calendars scope only', function () {
        $this->provider->shouldReceive('stateless')->once()->andReturnSelf();
        $this->provider->shouldReceive('setScopes')->once()
            ->with(['openid', 'email', 'https://www.googleapis.com/auth/calendar.app.created'])
            ->andReturnSelf();
        $this->provider->shouldReceive('refreshToken')->andReturn(new Token('t', 'r', 1, []));

        $this->client->refresh(IntegrationsFixtures::REFRESH_TOKEN);
    });
});

describe('the consent screen', function () {
    it('asks for offline access with a forced consent, carrying the state', function () {
        $this->provider->shouldReceive('with')->once()
            ->with(['access_type' => 'offline', 'prompt' => 'consent', 'state' => 'state-123'])
            ->andReturnSelf();
        $this->provider->shouldReceive('redirect')->once()
            ->andReturn(new RedirectResponse('https://accounts.google.com/o/oauth2/auth?state=state-123'));

        expect($this->client->authorizationUrl('state-123'))
            ->toBe('https://accounts.google.com/o/oauth2/auth?state=state-123');
    });
});

describe('talking to the token endpoint', function () {
    it('exchanges the code for the raw token response', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()->with('code-123')
            ->andReturn(['access_token' => 'a', 'refresh_token' => 'r']);

        expect($this->client->exchange('code-123'))->toBe(['access_token' => 'a', 'refresh_token' => 'r']);
    });

    it('answers an empty response for a token response that is not an object', function () {
        $this->provider->shouldReceive('getAccessTokenResponse')->once()->andReturn(null);

        expect($this->client->exchange('code-123'))->toBe([]);
    });

    it('reads the account email off the access token', function () {
        $this->provider->shouldReceive('userFromToken')->once()->with(IntegrationsFixtures::ACCESS_TOKEN)
            ->andReturn((new User)->map(['email' => IntegrationsFixtures::ACCOUNT_EMAIL]));

        expect($this->client->emailFor(IntegrationsFixtures::ACCESS_TOKEN))->toBe(IntegrationsFixtures::ACCOUNT_EMAIL);
    });

    it('answers an empty email when Google disclosed none', function () {
        $this->provider->shouldReceive('userFromToken')->once()->andReturn(new User);

        expect($this->client->emailFor(IntegrationsFixtures::ACCESS_TOKEN))->toBe('');
    });

    it('refreshes with the refresh token it was handed', function () {
        $token = new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, IntegrationsFixtures::REFRESH_TOKEN, 3600, []);
        $this->provider->shouldReceive('refreshToken')->once()->with(IntegrationsFixtures::REFRESH_TOKEN)->andReturn($token);

        expect($this->client->refresh(IntegrationsFixtures::REFRESH_TOKEN))->toBe($token);
    });
});

describe('revoking a token', function () {
    beforeEach(function () {
        $this->failureOf = function (): ?GoogleApiFailure {
            try {
                $this->client->revoke(IntegrationsFixtures::REFRESH_TOKEN);
            } catch (GoogleApiFailure $failure) {
                return $failure;
            }

            return null;
        };
    });

    it('treats the revocation as done when Google accepts it or no longer knows the token', function (int $status) {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response($status === 400 ? ['error' => 'invalid_token'] : '', $status)]);

        expect(($this->failureOf)())->toBeNull();

        Http::assertSentCount(1);
    })->with([200, 204, 400]);

    it('posts only the token, as a form, to the revoke endpoint', function () {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response('', 200)]);

        $this->client->revoke(IntegrationsFixtures::REFRESH_TOKEN);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://oauth2.googleapis.com/revoke'
            && $request->isForm()
            && $request->data() === ['token' => IntegrationsFixtures::REFRESH_TOKEN]);
    });

    it('sends no bearer credential', function () {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response('', 200)]);

        $this->client->revoke(IntegrationsFixtures::REFRESH_TOKEN);

        Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization'));
    });

    it('never builds a Socialite provider to revoke', function () {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response('', 200)]);
        $this->socialite->shouldNotReceive('buildProvider');

        $this->client->revoke(IntegrationsFixtures::REFRESH_TOKEN);
    });

    it('fails naming the call when Google answers any other status', function (int $status) {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::response('', $status)]);

        expect(($this->failureOf)()?->getMessage())
            ->toBe(GoogleApiFailure::unexpectedStatus('POST', 'https://oauth2.googleapis.com/revoke', $status)->getMessage());
    })->with([401, 403, 500, 503]);

    it('fails as unreachable when the connection drops', function () {
        Http::fake(['https://oauth2.googleapis.com/revoke' => Http::failedConnection('cURL error 28: Operation timed out')]);

        expect(($this->failureOf)()?->getMessage())
            ->toBe(GoogleApiFailure::unreachable('POST', 'https://oauth2.googleapis.com/revoke', ConnectionException::class)->getMessage());
    });

    it('never carries the token in the failure or its chain', function (Closure $answer) {
        Http::fake(['https://oauth2.googleapis.com/revoke' => $answer()]);

        $failure = ($this->failureOf)();

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure?->getMessage())->not->toContain(IntegrationsFixtures::REFRESH_TOKEN)
            ->and($failure?->getPrevious())->toBeNull();
    })->with([
        'an unexpected status' => [fn () => Http::response(['error' => 'token '.IntegrationsFixtures::REFRESH_TOKEN], 500)],
        'a dropped connection' => [fn () => Http::failedConnection('cURL error 28 for token='.IntegrationsFixtures::REFRESH_TOKEN)],
    ]);
});

it('declares the revoke endpoint it posts to', function () {
    expect(GoogleOAuthClient::REVOKE_URL)->toBe('https://oauth2.googleapis.com/revoke');
});

it('declares the calendar scope Mizita depends on', function () {
    expect(GoogleOAuthClient::CALENDAR_SCOPE)->toBe('https://www.googleapis.com/auth/calendar.app.created');
});
