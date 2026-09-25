<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AccountPendingReactivation extends DomainException implements DomainFailure
{
    private function __construct(
        public readonly string $accountId,
    ) {
        parent::__construct("Account [{$accountId}] is scheduled for deletion and must be reactivated before signing in.");
    }

    public static function forAccount(string $accountId): self
    {
        return new self($accountId);
    }

    public function errorCode(): string
    {
        return 'account_pending_reactivation';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
