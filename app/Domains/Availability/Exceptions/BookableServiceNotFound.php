<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class BookableServiceNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $serviceId, ?Throwable $previous = null): self
    {
        return new self("Service [{$serviceId}] is not bookable.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'bookable_service_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
