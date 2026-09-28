<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSubscriptionPlan extends DomainException implements DomainFailure
{
    public static function unknown(string $plan): self
    {
        return new self("[{$plan}] is not a subscription plan.");
    }

    public function errorCode(): string
    {
        return 'invalid_subscription_plan';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
