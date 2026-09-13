<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidAccountName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('An account name cannot be empty.');
    }

    public function errorCode(): string
    {
        return 'invalid_account_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
