<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAccountEmail extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('An account email cannot be empty.');
    }

    public static function malformed(string $email): self
    {
        return new self("[{$email}] is not a valid email address.");
    }

    public function errorCode(): string
    {
        return 'invalid_account_email';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
