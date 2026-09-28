<?php

declare(strict_types=1);

namespace App\Domains\Subscriptions\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSubscriptionBusinessSlug extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('A subscription needs the slug of the business it belongs to.');
    }

    public function errorCode(): string
    {
        return 'invalid_business_slug';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
