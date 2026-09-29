<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class SubscriptionBusinessNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $businessId): self
    {
        return new self("Business [{$businessId}] or its owner was not found.");
    }

    public function errorCode(): string
    {
        return 'business_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
