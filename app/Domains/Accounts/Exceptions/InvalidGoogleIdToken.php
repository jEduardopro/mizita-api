<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use DomainException;
use Throwable;

final class InvalidGoogleIdToken extends DomainException
{
    /**
     * The credential is not shaped like a JWT, so it cannot be an ID token.
     */
    public static function notAJsonWebToken(): self
    {
        return new self('The supplied credential is not a Google ID token.');
    }

    /**
     * Signature, issuer, audience or expiry did not check out.
     */
    public static function unverifiable(Throwable $previous): self
    {
        return new self('The Google ID token could not be verified.', previous: $previous);
    }

    public static function missingSubject(): self
    {
        return new self('The Google ID token carries no subject claim.');
    }
}
