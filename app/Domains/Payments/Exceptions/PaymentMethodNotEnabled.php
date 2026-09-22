<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentMethodNotEnabled extends DomainException implements DomainFailure
{
    public static function withId(string $paymentMethodId): self
    {
        return new self("Payment method [{$paymentMethodId}] is not enabled for this business.");
    }

    public function errorCode(): string
    {
        return 'payment_method_not_enabled';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
