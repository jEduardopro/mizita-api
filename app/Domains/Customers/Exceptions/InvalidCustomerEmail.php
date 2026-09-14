<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerEmail extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('The address offered is not a valid email address.');
    }

    public static function tooLong(): self
    {
        return new self('The address offered is longer than a customer email may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_customer_email';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
