<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A customer name cannot be empty.');
    }

    public static function tooLong(): self
    {
        return new self('The name offered is longer than a customer name may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_customer_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
