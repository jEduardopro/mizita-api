<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AccountDeletionEmailMismatch extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("The email typed to confirm the deletion of account [{$accountId}] does not match it.");
    }

    public function errorCode(): string
    {
        return 'account_deletion_email_mismatch';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
