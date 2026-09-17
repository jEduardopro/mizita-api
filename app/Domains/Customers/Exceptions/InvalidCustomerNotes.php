<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCustomerNotes extends DomainException implements DomainFailure
{
    public static function tooLong(int $maximum): self
    {
        return new self("Customer notes may not run past {$maximum} characters.");
    }

    public function errorCode(): string
    {
        return 'invalid_customer_notes';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
