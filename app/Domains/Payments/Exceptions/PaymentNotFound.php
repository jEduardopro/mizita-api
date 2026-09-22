<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class PaymentNotFound extends DomainException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Payment [{$id}] was not found.");
    }

    public function errorCode(): string
    {
        return 'payment_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
