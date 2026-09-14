<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Google;

use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

final class SocialiteGoogleIdentityVerifier implements GoogleIdentityVerifier
{
    private const DRIVER = 'google';

    private const JSON_WEB_TOKEN_PATTERN = '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/';

    private const JSON_WEB_TOKEN_MINIMUM_LENGTH = 101;

    public function __construct(
        private readonly SocialiteGoogleIdentity $identities,
    ) {}

    public function verify(string $idToken): GoogleIdentity
    {
        $this->guardAgainstOpaqueToken($idToken);

        try {
            /** @var GoogleProvider $provider */
            $provider = Socialite::driver(self::DRIVER);

            $user = $provider->userFromToken($idToken);
        } catch (Throwable $exception) {
            throw InvalidGoogleIdToken::unverifiable($exception);
        }

        return $this->identities->toGoogleIdentity($user);
    }

    private function guardAgainstOpaqueToken(string $idToken): void
    {
        if (strlen($idToken) < self::JSON_WEB_TOKEN_MINIMUM_LENGTH) {
            throw InvalidGoogleIdToken::notAJsonWebToken();
        }

        if (preg_match(self::JSON_WEB_TOKEN_PATTERN, $idToken) !== 1) {
            throw InvalidGoogleIdToken::notAJsonWebToken();
        }
    }
}
