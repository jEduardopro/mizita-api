<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class CheckoutSessionNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $sessionId): self
    {
        return new self("Checkout session [{$sessionId}] does not belong to this business.");
    }

    public function errorCode(): string
    {
        return 'checkout_session_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
