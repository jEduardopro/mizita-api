<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class CustomerEmailAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $email, ?Throwable $previous = null): self
    {
        return new self("A customer with email [{$email}] already exists.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'customer_email_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
