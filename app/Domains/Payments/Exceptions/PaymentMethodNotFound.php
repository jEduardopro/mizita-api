<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentMethodNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $paymentMethodId): self
    {
        return new self("Payment method [{$paymentMethodId}] was not found.");
    }

    public function errorCode(): string
    {
        return 'payment_method_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
