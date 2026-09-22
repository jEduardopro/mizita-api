<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TooManyPaymentItems extends DomainException implements DomainFailure
{
    public static function atMost(int $maximum): self
    {
        return new self("A payment cannot hold more than {$maximum} items.");
    }

    public function errorCode(): string
    {
        return 'too_many_payment_items';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
