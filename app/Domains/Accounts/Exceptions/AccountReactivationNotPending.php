<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AccountReactivationNotPending extends DomainException implements DomainFailure
{
    public static function inThisSession(): self
    {
        return new self('No account is waiting to be reactivated in this session.');
    }

    public function errorCode(): string
    {
        return 'account_reactivation_not_pending';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
