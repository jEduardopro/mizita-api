<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSubscriptionEndDate extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('A subscription needs the last day it includes.');
    }

    public static function malformed(string $value): self
    {
        return new self("[{$value}] is not a calendar date in the YYYY-MM-DD format.");
    }

    public function errorCode(): string
    {
        return 'invalid_subscription_end_date';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
