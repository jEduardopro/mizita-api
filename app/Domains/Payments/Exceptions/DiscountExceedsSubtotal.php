<?php

declare(strict_types=1);

namespace App\Domains\Payments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class DiscountExceedsSubtotal extends DomainException implements DomainFailure
{
    public static function of(int $discountCents, int $subtotalCents): self
    {
        return new self("A discount of [{$discountCents}] exceeds the subtotal of [{$subtotalCents}].");
    }

    public function errorCode(): string
    {
        return 'discount_exceeds_subtotal';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
