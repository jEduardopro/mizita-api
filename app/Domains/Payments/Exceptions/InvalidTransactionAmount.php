<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidTransactionAmount extends DomainException implements DomainFailure
{
    public static function notPositive(int $cents): self
    {
        return new self("[{$cents}] is not a valid transaction amount.");
    }

    public static function tooLarge(int $cents, int $maximum): self
    {
        return new self("[{$cents}] exceeds the maximum transaction amount of {$maximum}.");
    }

    public function errorCode(): string
    {
        return 'invalid_transaction_amount';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
