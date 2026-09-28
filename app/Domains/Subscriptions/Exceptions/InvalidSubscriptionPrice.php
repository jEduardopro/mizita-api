<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSubscriptionPrice extends DomainException implements DomainFailure
{
    public static function negative(int $amount): self
    {
        return new self("A subscription price cannot be negative, [{$amount}] given.");
    }

    public static function malformed(string $amount): self
    {
        return new self("[{$amount}] is not a whole amount in minor currency units.");
    }

    public function errorCode(): string
    {
        return 'invalid_subscription_price';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
