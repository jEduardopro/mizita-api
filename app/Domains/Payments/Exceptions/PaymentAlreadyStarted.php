<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentAlreadyStarted extends DomainException implements DomainFailure
{
    public static function withId(string $paymentId): self
    {
        return new self("Payment [{$paymentId}] already holds money and can no longer be changed.");
    }

    public function errorCode(): string
    {
        return 'payment_already_started';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
