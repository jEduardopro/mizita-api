<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class AccountSignsInWithSocialIdentity extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("Account [{$accountId}] signs in through a linked social identity.");
    }

    public function errorCode(): string
    {
        return 'account_signs_in_with_social_identity';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
