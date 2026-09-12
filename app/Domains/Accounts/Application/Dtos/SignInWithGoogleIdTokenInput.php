<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

/**
 * Input boundary for SignInWithGoogleIdToken. Carries the raw, unverified
 * credential: verifying it is the use case's first act.
 */
final readonly class SignInWithGoogleIdTokenInput
{
    public function __construct(
        public string $idToken,
    ) {}
}
