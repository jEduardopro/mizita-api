<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerPhone extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('The number offered is not a valid customer phone.');
    }

    public static function tooLong(): self
    {
        return new self('The number offered is longer than a customer phone may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_customer_phone';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
