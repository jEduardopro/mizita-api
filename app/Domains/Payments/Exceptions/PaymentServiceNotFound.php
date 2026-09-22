<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentServiceNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $serviceId): self
    {
        return new self("Service [{$serviceId}] was not found for this business.");
    }

    public function errorCode(): string
    {
        return 'payment_service_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
