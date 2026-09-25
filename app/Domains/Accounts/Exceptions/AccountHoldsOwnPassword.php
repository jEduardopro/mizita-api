<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AccountHoldsOwnPassword extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("Account [{$accountId}] already holds a password of its own.");
    }

    public function errorCode(): string
    {
        return 'account_holds_own_password';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
