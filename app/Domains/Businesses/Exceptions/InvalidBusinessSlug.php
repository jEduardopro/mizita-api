<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

/**
 * A stored slug does not have the shape a slug is allowed to have.
 *
 * Raised on the restore path, so in practice it means a row was written before
 * the value object existed, or by something that bypassed it.
 */
final class InvalidBusinessSlug extends DomainException implements DomainFailure
{
    public static function forValue(string $value): self
    {
        return new self("[{$value}] is not a valid business slug.");
    }

    public static function reserved(string $value): self
    {
        return new self("[{$value}] is a reserved slug.");
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
