<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentTransactionNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $transactionId): self
    {
        return new self("Payment transaction [{$transactionId}] was not found.");
    }

    public function errorCode(): string
    {
        return 'payment_transaction_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
