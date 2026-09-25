<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Infrastructure\Google;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\SocialiteManager;
use Laravel\Socialite\Two\GoogleProvider;
use Laravel\Socialite\Two\Token;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\Response as Status;
use Throwable;

final class GoogleOAuthClient
{
    public const CALENDAR_SCOPE = 'https://www.googleapis.com/auth/calendar.app.created';

    public const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    private const SCOPES = ['openid', 'email', self::CALENDAR_SCOPE];

    private const AUTHORIZATION_PARAMETERS = [
        'access_type' => 'offline',
        'prompt' => 'consent',
    ];

    public function __construct(
        private readonly SocialiteManager $socialite,
        private readonly string $clientId,
        #[SensitiveParameter] private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly int $timeoutSeconds,
    ) {}

    public function authorizationUrl(string $state): string
    {
        return $this->provider()
            ->with([...self::AUTHORIZATION_PARAMETERS, 'state' => $state])
            ->redirect()
            ->getTargetUrl();
    }

    /**
     * @return array<string, mixed>
     */
    public function exchange(#[SensitiveParameter] string $code): array
    {
        $response = $this->provider()->getAccessTokenResponse($code);

        return is_array($response) ? $response : [];
    }

    public function emailFor(#[SensitiveParameter] string $accessToken): string
    {
        return (string) $this->provider()->userFromToken($accessToken)->getEmail();
    }

    public function refresh(#[SensitiveParameter] string $refreshToken): Token
    {
        return $this->provider()->refreshToken($refreshToken);
    }

    /**
     * @throws GoogleApiFailure
     */
    public function revoke(#[SensitiveParameter] string $token): void
    {
        $response = $this->postRevocation($token);

        if ($response->successful() || $response->status() === Status::HTTP_BAD_REQUEST) {
            return;
        }

        throw GoogleApiFailure::unexpectedStatus('POST', self::REVOKE_URL, $response->status());
    }

    private function postRevocation(#[SensitiveParameter] string $token): Response
    {
        try {
            return Http::asForm()
                ->timeout($this->timeoutSeconds)
                ->post(self::REVOKE_URL, ['token' => $token]);
        } catch (Throwable $failure) {
            throw GoogleApiFailure::unreachable('POST', self::REVOKE_URL, $failure::class);
        }
    }

    private function provider(): GoogleProvider
    {
        $provider = $this->socialite->buildProvider(GoogleProvider::class, [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect' => $this->redirectUri,
            'guzzle' => ['timeout' => $this->timeoutSeconds],
        ]);

        $provider->stateless()->setScopes(self::SCOPES);

        return $provider;
    }
}
