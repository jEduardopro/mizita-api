<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Google;

use App\Domains\Accounts\Contracts\GoogleIdentityVerifier;
use App\Domains\Accounts\Exceptions\InvalidGoogleIdToken;
use App\Domains\Accounts\ValueObjects\GoogleIdentity;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GoogleProvider;
use Throwable;

/**
 * Socialite's GoogleProvider is the verifier: userFromToken() checks the
 * signature against Google's JWKS, the issuer, the audience against our client
 * id, and the expiry. None of that is reimplemented here.
 */
final class SocialiteGoogleIdentityVerifier implements GoogleIdentityVerifier
{
    private const DRIVER = 'google';

    private const JSON_WEB_TOKEN_PATTERN = '/^[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+$/';

    /**
     * Mirrors GoogleProvider::isJwtToken(), which only takes the verifying
     * branch for a token longer than 100 characters.
     */
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

    /**
     * A security rule, not input validation, which is why it lives behind the
     * port rather than in a FormRequest: it has to hold for every caller.
     * Socialite falls back to Google's userinfo endpoint for a token it does not
     * recognise as a JWT, and that endpoint performs no audience check - so an
     * opaque access token minted for a different Google client would otherwise
     * sign that person in here.
     */
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
