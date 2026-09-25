<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class CalendarBusinessNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $businessId, ?Throwable $previous = null): self
    {
        return new self("Business [{$businessId}] was not found.", 0, $previous);
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
