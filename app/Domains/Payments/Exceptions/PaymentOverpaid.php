<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentOverpaid extends DomainException implements DomainFailure
{
    public static function byCents(int $amountCents, int $balanceCents): self
    {
        return new self("A payment of [{$amountCents}] exceeds the outstanding balance of [{$balanceCents}].");
    }

    public function errorCode(): string
    {
        return 'payment_overpaid';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
