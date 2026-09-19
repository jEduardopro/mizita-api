<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidSlotGranularity extends DomainException implements DomainFailure
{
    public static function tooShort(int $minutes): self
    {
        return new self("[{$minutes}] is a shorter step than slots may be offered on.");
    }

    public static function tooLong(int $minutes): self
    {
        return new self("[{$minutes}] is a longer step than slots may be offered on.");
    }

    public static function notAMultiple(int $minutes, int $step): self
    {
        return new self("[{$minutes}] is not a multiple of [{$step}] minutes.");
    }

    public function errorCode(): string
    {
        return 'invalid_slot_granularity';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
