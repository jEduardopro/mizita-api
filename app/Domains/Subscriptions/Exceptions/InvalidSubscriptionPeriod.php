<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSubscriptionPeriod extends DomainException implements DomainFailure
{
    public static function inverted(): self
    {
        return new self('A subscription period cannot end before it starts.');
    }

    public static function alreadyEnded(): self
    {
        return new self('A subscription period must end in the future.');
    }

    public function errorCode(): string
    {
        return 'invalid_subscription_period';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
