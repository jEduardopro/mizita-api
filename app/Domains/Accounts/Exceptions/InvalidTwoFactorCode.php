<?php

declare(strict_types=1);

namespace App\Domains\Accounts\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidTwoFactorCode extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId): self
    {
        return new self("The two factor code given for account [{$accountId}] is not valid.");
    }

    public static function conflictingProofs(): self
    {
        return new self('A two factor code and a recovery code cannot be given together.');
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("The two factor code exceeds {$maximumLength} characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_two_factor_code';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
