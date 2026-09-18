<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class AppointmentCustomerNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id, ?Throwable $previous = null): self
    {
        return new self("Customer [{$id}] is not one of this business.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'appointment_customer_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
