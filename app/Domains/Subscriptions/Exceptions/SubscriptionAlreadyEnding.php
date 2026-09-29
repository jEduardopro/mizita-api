<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class SubscriptionAlreadyEnding extends DomainException implements DomainFailure
{
    public static function withId(string $subscriptionId): self
    {
        return new self("Subscription [{$subscriptionId}] is already set to end with its current period.");
    }

    public function errorCode(): string
    {
        return 'subscription_already_ending';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
