<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

/**
 * Another business already trades under this name.
 *
 * Raised twice on purpose: once by the use case, which reads before it writes,
 * and again by the repository when the partial unique index fires because two
 * signups raced past that read. The second is the authoritative one - the read
 * only exists so the common case gets a message instead of a stack trace.
 */
final class BusinessNameAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $name, ?Throwable $previous = null): self
    {
        return new self("A business named [{$name}] already exists.", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'business_name_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
