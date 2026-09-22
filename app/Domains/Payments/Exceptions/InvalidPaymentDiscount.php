<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPaymentDiscount extends DomainException implements DomainFailure
{
    public static function unknownType(string $type): self
    {
        return new self("[{$type}] is not a supported discount type.");
    }

    public static function percentageOutOfRange(int $basisPoints): self
    {
        return new self("[{$basisPoints}] is outside the supported discount percentage range.");
    }

    public static function negativeAmount(int $cents): self
    {
        return new self("[{$cents}] is not a valid discount amount.");
    }

    public function errorCode(): string
    {
        return 'invalid_payment_discount';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
