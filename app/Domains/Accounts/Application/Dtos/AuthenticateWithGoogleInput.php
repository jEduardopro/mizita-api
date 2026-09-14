<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

use App\Domains\Accounts\ValueObjects\GoogleIdentity;

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
