<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidRecoveryCode extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("The recovery code given for account [{$accountId}] is not valid.");
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("The recovery code exceeds {$maximumLength} characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_recovery_code';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
