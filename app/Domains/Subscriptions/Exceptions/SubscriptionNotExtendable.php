<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class SubscriptionNotExtendable extends DomainException implements DomainFailure
{
    public static function openEnded(): self
    {
        return new self('A subscription with no end date cannot be extended.');
    }

    public static function notAnExtension(): self
    {
        return new self('A subscription can only be extended to a date after its current end.');
    }

    public function errorCode(): string
    {
        return 'subscription_not_extendable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
