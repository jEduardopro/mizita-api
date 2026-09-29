<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSubscriptionPlan extends DomainException implements DomainFailure
{
    public static function unknown(string $planId): self
    {
        return new self("[{$planId}] is not a plan on offer.");
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
