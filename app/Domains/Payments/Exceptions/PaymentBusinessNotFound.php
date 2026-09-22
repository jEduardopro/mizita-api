<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentBusinessNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $businessId): self
    {
        return new self("Business [{$businessId}] was not found.");
    }

    public function errorCode(): string
    {
        return 'payment_business_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
