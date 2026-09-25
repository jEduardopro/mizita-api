<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class IncorrectAccountPassword extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("The password given to confirm the deletion of account [{$accountId}] is not correct.");
    }

    public function errorCode(): string
    {
        return 'incorrect_account_password';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
