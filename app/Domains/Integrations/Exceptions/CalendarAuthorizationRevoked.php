<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class CalendarAuthorizationRevoked extends RuntimeException implements DomainFailure
{
    public static function forConnection(string $connectionId, ?Throwable $previous = null): self
    {
        return new self("The authorization behind calendar connection [{$connectionId}] was revoked.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'calendar_authorization_revoked';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
