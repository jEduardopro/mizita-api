<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\ValueObjects\GoogleIdentity;

/**
 * Input boundary for AuthenticateWithGoogle. Framework free: whichever entry
 * point verified the caller maps its result into this object.
 *
 * Only constructible from a GoogleIdentity, on purpose. That value object is
 * where the subject and the email are proven present, so sealing the
 * constructor makes those guarantees structural: there is no second way to
 * build an input, and therefore no second set of checks to keep in sync.
 */
final readonly class AuthenticateWithGoogleInput
{
    private function __construct(
        public string $googleUserId,
        public string $email,
        public bool $emailVerified,
        public string $name,
    ) {}

    public static function fromGoogleIdentity(GoogleIdentity $identity): self
    {
        return new self(
            googleUserId: $identity->sub,
            email: $identity->email,
            emailVerified: $identity->emailVerified,
            name: $identity->name,
        );
    }
}
