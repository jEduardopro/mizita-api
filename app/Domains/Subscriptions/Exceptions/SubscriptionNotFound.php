<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class SubscriptionNotFound extends RuntimeException implements DomainFailure
{
    public static function inEffectFor(string $businessId): self
    {
        return new self("Business [{$businessId}] has no subscription in effect.");
    }

    public function errorCode(): string
    {
        return 'subscription_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
