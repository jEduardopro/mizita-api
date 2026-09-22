<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentAlreadySettled extends DomainException implements DomainFailure
{
    public static function withId(string $paymentId): self
    {
        return new self("Payment [{$paymentId}] is already settled.");
    }

    public function errorCode(): string
    {
        return 'payment_already_settled';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
