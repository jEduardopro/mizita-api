<?php

declare(strict_types=1);

use App\Domains\Integrations\Exceptions\CalendarAuthorizationRevoked;
use App\Domains\Integrations\Infrastructure\Google\GoogleAccessTokens;
use App\Domains\Integrations\Infrastructure\Google\GoogleApiFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use Laravel\Socialite\Two\Token;
use Tests\Support\FakeClock;
use Tests\TestCase;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\GoogleOAuthDoubles;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\IntegrationsFixtures;
use Tests\Unit\Domains\Integrations\Infrastructure\Support\StoredCalendarCredentials;

uses(TestCase::class);

beforeEach(function () {
    $this->credentials = StoredCalendarCredentials::install();
    $this->provider = GoogleOAuthDoubles::provider();
    $this->tokens = new GoogleAccessTokens(
        GoogleOAuthDoubles::clientOver($this->provider),
        new FakeClock(IntegrationsFixtures::now()),
    );

    $this->failureOf = function (callable $action): ?Throwable {
        try {
            $action();
        } catch (Throwable $failure) {
            return $failure;
        }

        return null;
    };
});

afterEach(function () {
    $this->credentials->uninstall();
});

describe('an access token that is still fresh', function () {
    it('hands back the stored token without asking Google', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T10:02:00+00:00');
        $this->provider->shouldNotReceive('refreshToken');

        expect($this->tokens->current(IntegrationsFixtures::CONNECTION_ID))->toBe(IntegrationsFixtures::ACCESS_TOKEN)
            ->and($this->credentials->updates)->toBe([]);
    });

    it('reads the credentials of a soft deleted connection too, so a clean up can still reach Google', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T11:00:00+00:00', deletedAt: '2026-03-29 09:00:00');

        expect($this->tokens->current(IntegrationsFixtures::CONNECTION_ID))->toBe(IntegrationsFixtures::ACCESS_TOKEN)
            ->and($this->credentials->selects[0])->not->toContain('deleted_at');
    });
});

describe('an access token that has expired or is about to', function () {
    beforeEach(function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->with(IntegrationsFixtures::REFRESH_TOKEN)
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '', 3599, []));
    });

    it('refreshes a token that has already expired', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T09:00:00+00:00');

        expect($this->tokens->current(IntegrationsFixtures::CONNECTION_ID))->toBe(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN);
    });

    it('refreshes a token with exactly one minute left', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T10:01:00+00:00');

        expect($this->tokens->current(IntegrationsFixtures::CONNECTION_ID))->toBe(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN);
    });

    it('stores the refreshed token encrypted, with the expiry Google granted from now', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T09:00:00+00:00');

        $this->tokens->current(IntegrationsFixtures::CONNECTION_ID);

        expect($this->credentials->storedAccessToken())->toBe(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN)
            ->and($this->credentials->storedExpiry())->toEqual(new DateTimeImmutable('2026-03-29T10:59:59+00:00'))
            ->and($this->credentials->updates[0]['access_token'])->not->toBe(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN);
    });

    it('keeps the stored refresh token when Google does not rotate it', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T09:00:00+00:00');

        $this->tokens->current(IntegrationsFixtures::CONNECTION_ID);

        expect($this->credentials->storedRefreshToken())->toBe(IntegrationsFixtures::REFRESH_TOKEN);
    });
});

describe('forcing a refresh', function () {
    it('refreshes even a token that is still fresh', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T11:00:00+00:00');
        $this->provider->shouldReceive('refreshToken')->once()
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '', 3600, []));

        expect($this->tokens->refreshed(IntegrationsFixtures::CONNECTION_ID))->toBe(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN);
    });

    it('stores the refresh token Google rotated in', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T11:00:00+00:00');
        $this->provider->shouldReceive('refreshToken')->once()
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '1//rotated-refresh-token', 3600, []));

        $this->tokens->refreshed(IntegrationsFixtures::CONNECTION_ID);

        expect($this->credentials->storedRefreshToken())->toBe('1//rotated-refresh-token');
    });

    it('never stores an expiry in the past when Google answers a negative lifetime', function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T11:00:00+00:00');
        $this->provider->shouldReceive('refreshToken')->once()
            ->andReturn(new Token(IntegrationsFixtures::REFRESHED_ACCESS_TOKEN, '', -30, []));

        $this->tokens->refreshed(IntegrationsFixtures::CONNECTION_ID);

        expect($this->credentials->storedExpiry())->toEqual(IntegrationsFixtures::now());
    });
});

describe('a refresh Google refuses', function () {
    beforeEach(function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T09:00:00+00:00');
    });

    it('treats a revoked grant as a revoked authorization', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andThrow(GoogleOAuthDoubles::tokenEndpointRejection(400, '{"error":"invalid_grant","error_description":"Token has been expired or revoked."}'));

        $failure = ($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID));

        expect($failure)->toBeInstanceOf(CalendarAuthorizationRevoked::class)
            ->and($failure->errorCode())->toBe('calendar_authorization_revoked')
            ->and($failure->kind())->toBe(DomainFailureKind::Conflict)
            ->and($failure->getMessage())->toContain(IntegrationsFixtures::CONNECTION_ID);
    });

    it('treats an unauthorized client as a revoked authorization', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andThrow(GoogleOAuthDoubles::tokenEndpointRejection(401, '{"error":"unauthorized_client"}'));

        expect(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID))
            ->toThrow(CalendarAuthorizationRevoked::class);
    });

    it('keeps any other bad request apart from a revocation', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andThrow(GoogleOAuthDoubles::tokenEndpointRejection(400, '{"error":"invalid_request"}'));

        $failure = ($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain('HTTP 400');
    });

    it('keeps a forbidden answer apart from a revocation', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andThrow(GoogleOAuthDoubles::tokenEndpointRejection(403, '{"error":"access_denied"}'));

        expect(($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID)))
            ->toBeInstanceOf(GoogleApiFailure::class);
    });

    it('stores nothing when the refresh is refused', function () {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andThrow(GoogleOAuthDoubles::tokenEndpointRejection(400, '{"error":"invalid_grant"}'));

        ($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID));

        expect($this->credentials->updates)->toBe([])
            ->and($this->credentials->storedAccessToken())->toBe(IntegrationsFixtures::ACCESS_TOKEN);
    });

    it('never carries a token in the message or the chain of a refusal', function (int $status, string $body) {
        $this->provider->shouldReceive('refreshToken')->once()
            ->andThrow(GoogleOAuthDoubles::tokenEndpointRejection($status, $body));

        $failure = ($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID));

        expect($failure->getMessage())
            ->not->toContain(IntegrationsFixtures::REFRESH_TOKEN)
            ->not->toContain(IntegrationsFixtures::ACCESS_TOKEN)
            ->and($failure->getPrevious())->toBeNull();
    })->with([
        'a revoked grant' => [400, '{"error":"invalid_grant"}'],
        'an unauthorized client' => [401, '{"error":"unauthorized_client"}'],
        'a forbidden answer' => [403, '{"error":"access_denied"}'],
    ]);
});

describe('a refresh that never reaches Google', function () {
    beforeEach(function () {
        $this->credentials->hold(accessTokenExpiresAt: '2026-03-29T09:00:00+00:00');
        $this->provider->shouldReceive('refreshToken')->once()->andThrow(new ConnectException(
            'cURL error 28: Operation timed out refresh_token='.IntegrationsFixtures::REFRESH_TOKEN,
            new Request('POST', 'https://oauth2.googleapis.com/token'),
        ));
    });

    it('rethrows it as a Google failure naming the connection and the cause', function () {
        $failure = ($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain(IntegrationsFixtures::CONNECTION_ID)
            ->and($failure->getMessage())->toContain(ConnectException::class);
    });

    it('drops the transport message that carried the refresh token', function () {
        $failure = ($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::CONNECTION_ID));

        expect($failure->getMessage())->not->toContain(IntegrationsFixtures::REFRESH_TOKEN)
            ->and($failure->getPrevious())->toBeNull();
    });
});

describe('the refresh token itself', function () {
    it('reads the stored refresh token back in clear', function () {
        $this->credentials->hold();

        expect($this->tokens->refreshTokenOf(IntegrationsFixtures::CONNECTION_ID))->toBe(IntegrationsFixtures::REFRESH_TOKEN);
    });
});

describe('a connection with no stored credentials', function () {
    it('fails with a Google failure naming the connection', function (string $method) {
        $failure = ($this->failureOf)(fn () => $this->tokens->{$method}(IntegrationsFixtures::OTHER_CONNECTION_ID));

        expect($failure)->toBeInstanceOf(GoogleApiFailure::class)
            ->and($failure->getMessage())->toContain(IntegrationsFixtures::OTHER_CONNECTION_ID);
    })->with(['current', 'refreshed', 'refreshTokenOf']);

    it('never reads the credentials of another connection', function () {
        $this->credentials->hold();

        expect(($this->failureOf)(fn () => $this->tokens->current(IntegrationsFixtures::OTHER_CONNECTION_ID)))
            ->toBeInstanceOf(GoogleApiFailure::class);
    });
});
