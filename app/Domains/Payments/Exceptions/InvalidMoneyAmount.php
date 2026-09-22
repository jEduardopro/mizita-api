<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidMoneyAmount extends DomainException implements DomainFailure
{
    public static function negative(int $cents): self
    {
        return new self("[{$cents}] is not a valid money amount.");
    }

    public static function tooLarge(int $cents, int $maximum): self
    {
        return new self("[{$cents}] exceeds the maximum money amount of {$maximum}.");
    }

    public static function malformed(string $value): self
    {
        return new self("[{$value}] is not a well formed decimal money amount.");
    }

    public function errorCode(): string
    {
        return 'invalid_money_amount';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
