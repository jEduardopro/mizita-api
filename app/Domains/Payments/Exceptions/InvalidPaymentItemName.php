<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPaymentItemName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A payment item name cannot be empty.');
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("A payment item name cannot exceed {$maximumLength} characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_payment_item_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
