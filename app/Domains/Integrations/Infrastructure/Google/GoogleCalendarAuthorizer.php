<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use App\Domains\Integrations\Contracts\CalendarAuthorizer;
use App\Domains\Integrations\Exceptions\CalendarAuthorizationFailed;
use App\Domains\Integrations\Exceptions\CalendarScopeNotGranted;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use App\Shared\Contracts\Clock;
use DateInterval;
use SensitiveParameter;
use Throwable;

final class GoogleCalendarAuthorizer implements CalendarAuthorizer
{
    private const SCOPE_SEPARATOR = ' ';

    public function __construct(
        private readonly GoogleOAuthClient $oauth,
        private readonly Clock $clock,
    ) {}

    public function authorizationUrl(string $state): string
    {
        return $this->oauth->authorizationUrl($state);
    }

    public function exchange(#[SensitiveParameter] string $code): CalendarGrant
    {
        $response = $this->tokenResponseFor($code);

        try {
            return $this->grantFrom($response);
        } catch (Throwable $refusal) {
            $this->discardIssuedTokens($response);

            throw $refusal;
        }
    }

    /**
     * @param  array<string, mixed>  $response
     *
     * @throws CalendarScopeNotGranted
     * @throws CalendarAuthorizationFailed
     */
    private function grantFrom(#[SensitiveParameter] array $response): CalendarGrant
    {
        self::assertCalendarScopeGranted($response);

        $accessToken = self::textOf($response, 'access_token');
        $refreshToken = self::textOf($response, 'refresh_token');

        if ($accessToken === '') {
            throw CalendarAuthorizationFailed::exchangeFailed();
        }

        if ($refreshToken === '') {
            throw CalendarAuthorizationFailed::missingRefreshToken();
        }

        return new CalendarGrant(
            accountEmail: $this->accountEmailFor($accessToken),
            tokens: new CalendarTokens(
                accessToken: $accessToken,
                refreshToken: $refreshToken,
                accessTokenExpiresAt: $this->clock->now()->add(
                    new DateInterval('PT'.max(0, (int) ($response['expires_in'] ?? 0)).'S'),
                ),
            ),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenResponseFor(#[SensitiveParameter] string $code): array
    {
        try {
            return $this->oauth->exchange($code);
        } catch (Throwable) {
            throw CalendarAuthorizationFailed::exchangeFailed();
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function discardIssuedTokens(#[SensitiveParameter] array $response): void
    {
        $token = self::revocableTokenOf($response);

        if ($token === '') {
            return;
        }

        try {
            $this->oauth->revoke($token);
        } catch (Throwable) {
            return;
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function revocableTokenOf(#[SensitiveParameter] array $response): string
    {
        $refreshToken = self::textOf($response, 'refresh_token');

        return $refreshToken !== '' ? $refreshToken : self::textOf($response, 'access_token');
    }

    private function accountEmailFor(#[SensitiveParameter] string $accessToken): string
    {
        try {
            $email = trim($this->oauth->emailFor($accessToken));
        } catch (Throwable) {
            throw CalendarAuthorizationFailed::missingAccountEmail();
        }

        if ($email === '') {
            throw CalendarAuthorizationFailed::missingAccountEmail();
        }

        return $email;
    }

    /**
     * @param  array<string, mixed>  $response
     *
     * @throws CalendarScopeNotGranted
     */
    private static function assertCalendarScopeGranted(array $response): void
    {
        $granted = explode(self::SCOPE_SEPARATOR, self::textOf($response, 'scope'));

        if (! in_array(GoogleOAuthClient::CALENDAR_SCOPE, $granted, true)) {
            throw CalendarScopeNotGranted::forScope(GoogleOAuthClient::CALENDAR_SCOPE);
        }
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private static function textOf(array $response, string $key): string
    {
        $value = $response[$key] ?? '';

        return is_string($value) ? $value : '';
    }
}
