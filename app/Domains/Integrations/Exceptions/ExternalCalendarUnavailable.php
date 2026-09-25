<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class ExternalCalendarUnavailable extends RuntimeException implements DomainFailure
{
    public static function forConnection(string $connectionId, ?Throwable $previous = null): self
    {
        return new self("The external calendar of connection [{$connectionId}] could not be reached.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'external_calendar_unavailable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
