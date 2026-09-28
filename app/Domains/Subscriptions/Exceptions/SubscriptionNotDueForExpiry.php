<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class SubscriptionNotDueForExpiry extends DomainException implements DomainFailure
{
    public static function withId(string $subscriptionId): self
    {
        return new self("Subscription [{$subscriptionId}] has not reached its end yet.");
    }

    public function errorCode(): string
    {
        return 'subscription_not_due_for_expiry';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
