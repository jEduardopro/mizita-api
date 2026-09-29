<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class CheckoutAlreadyStarted extends DomainException implements DomainFailure
{
    public static function forBusiness(string $businessId, ?Throwable $previous = null): self
    {
        return new self("A checkout for business [{$businessId}] was started concurrently.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'checkout_already_started';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
