<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TwoFactorRequired extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("Account [{$accountId}] requires a second factor to sign in.");
    }

    public function errorCode(): string
    {
        return 'two_factor_required';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Unauthenticated;
    }
}
