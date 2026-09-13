<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

/**
 * The value offered is not an IANA time zone identifier PHP can resolve.
 *
 * A booking product computes every local time from the business time zone, so
 * an unresolvable one is not a cosmetic defect: it makes every schedule the
 * business ever publishes wrong.
 */
final class InvalidBusinessTimezone extends DomainException implements DomainFailure
{
    public static function forValue(string $value): self
    {
        return new self("[{$value}] is not a valid IANA time zone identifier.");
    }

    public function errorCode(): string
    {
        return 'invalid_timezone';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
