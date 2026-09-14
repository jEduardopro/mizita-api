<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class InvalidGoogleIdToken extends DomainException implements DomainFailure
{
    public static function notAJsonWebToken(): self
    {
        return new self('The supplied credential is not a Google ID token.');
    }

    public static function unverifiable(Throwable $previous): self
    {
        return new self('The Google ID token could not be verified.', previous: $previous);
    }

    public static function missingSubject(): self
    {
        return new self('The Google ID token carries no subject claim.');
    }

    public function errorCode(): string
    {
        return 'google_invalid_id_token';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Unauthenticated;
    }
}
