<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class SubscriptionNotActive extends DomainException implements DomainFailure
{
    public static function withId(string $subscriptionId): self
    {
        return new self("Subscription [{$subscriptionId}] does not grant access right now.");
    }

    public function errorCode(): string
    {
        return 'subscription_not_active';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
