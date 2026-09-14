<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Application\Dtos;

/**
 * Carries the raw, unverified credential: verifying it is the use case's first act.
 */
final readonly class SignInWithGoogleIdTokenInput
{
    public function __construct(
        public string $idToken,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            idToken: (string) $payload['id_token'],
        );
    }
}
