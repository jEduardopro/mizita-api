<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AccountNotScheduledForDeletion extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("Account [{$accountId}] is not scheduled for deletion.");
    }

    public function errorCode(): string
    {
        return 'account_not_scheduled_for_deletion';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
