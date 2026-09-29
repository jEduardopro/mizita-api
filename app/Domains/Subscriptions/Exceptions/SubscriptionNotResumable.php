<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class SubscriptionNotResumable extends DomainException implements DomainFailure
{
    public static function withId(string $subscriptionId): self
    {
        return new self("Subscription [{$subscriptionId}] has no pending cancellation it could resume from.");
    }

    public function errorCode(): string
    {
        return 'subscription_not_resumable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
