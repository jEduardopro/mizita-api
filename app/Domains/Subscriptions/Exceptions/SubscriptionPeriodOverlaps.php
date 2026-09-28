<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class SubscriptionPeriodOverlaps extends DomainException implements DomainFailure
{
    public static function withAnotherPeriod(?Throwable $previous = null): self
    {
        return new self('That subscription period overlaps another period of the same business.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'subscription_period_overlaps';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
