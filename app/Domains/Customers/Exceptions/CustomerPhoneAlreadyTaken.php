<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CustomerPhoneAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $number): self
    {
        return new self("A customer with phone number [{$number}] already exists.");
    }

    public function errorCode(): string
    {
        return 'customer_phone_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
