<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class VoidExceedsPaidAmount extends DomainException implements DomainFailure
{
    public static function byCents(int $amountCents, int $paidCents): self
    {
        return new self("A void of [{$amountCents}] exceeds the collected amount of [{$paidCents}].");
    }

    public function errorCode(): string
    {
        return 'void_exceeds_paid_amount';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
