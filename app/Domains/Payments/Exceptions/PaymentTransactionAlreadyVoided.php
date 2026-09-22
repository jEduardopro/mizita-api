<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentTransactionAlreadyVoided extends DomainException implements DomainFailure
{
    public static function withId(string $transactionId): self
    {
        return new self("Payment transaction [{$transactionId}] is already voided.");
    }

    public function errorCode(): string
    {
        return 'payment_transaction_already_voided';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
