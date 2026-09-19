<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidLeadTime extends DomainException implements DomainFailure
{
    public static function negative(int $minutes): self
    {
        return new self("[{$minutes}] is not a lead time a customer can be held to.");
    }

    public static function tooLong(int $minutes): self
    {
        return new self("[{$minutes}] is further ahead than a lead time may require.");
    }

    public function errorCode(): string
    {
        return 'invalid_lead_time';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
