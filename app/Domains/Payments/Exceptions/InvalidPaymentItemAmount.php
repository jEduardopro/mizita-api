<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPaymentItemAmount extends DomainException implements DomainFailure
{
    public static function negative(int $cents): self
    {
        return new self("[{$cents}] is not a valid payment item amount.");
    }

    public static function tooLarge(int $cents, int $maximum): self
    {
        return new self("[{$cents}] exceeds the maximum payment item amount of {$maximum}.");
    }

    public function errorCode(): string
    {
        return 'invalid_payment_item_amount';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
