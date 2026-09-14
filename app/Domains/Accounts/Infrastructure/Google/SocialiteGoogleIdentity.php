<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Infrastructure\Google;

use App\Domains\Accounts\ValueObjects\GoogleIdentity;
use Laravel\Socialite\AbstractUser as SocialiteUser;

/**
 * One translator serves both entry points: GoogleProvider::mapUserToObject()
 * calls setRaw(), so the raw claims are present whether they came from a
 * verified ID token or from the userinfo endpoint.
 */
final class SocialiteGoogleIdentity
{
    private const EMAIL_VERIFIED_CLAIM = 'email_verified';

    public function toGoogleIdentity(SocialiteUser $user): GoogleIdentity
    {
        return new GoogleIdentity(
            sub: (string) $user->getId(),
            email: (string) $user->getEmail(),
            emailVerified: $this->hasVerifiedEmail($user),
            name: (string) $user->getName(),
            avatarUrl: $user->getAvatar(),
        );
    }

    /**
     * Google sends a boolean here, but has historically sent the string "true"
     * from the userinfo endpoint. Anything it does not recognise reads as
     * unverified, which is the safe direction for this flag.
     */
    private function hasVerifiedEmail(SocialiteUser $user): bool
    {
        $claim = $user->getRaw()[self::EMAIL_VERIFIED_CLAIM] ?? false;

        return filter_var($claim, FILTER_VALIDATE_BOOLEAN);
    }
}
