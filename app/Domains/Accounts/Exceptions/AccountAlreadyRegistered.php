<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

/**
 * Another request registered this address first.
 *
 * This is the storage layer's uniqueness rule reaching the domain in domain
 * terms. It says nothing about what to do next: a caller racing itself should
 * adopt the winner's account, not report a conflict.
 */
final class AccountAlreadyRegistered extends DomainException implements DomainFailure
{
    public static function withEmail(string $email, ?Throwable $previous = null): self
    {
        return new self("An account already exists for [{$email}].", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'account_already_registered';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
