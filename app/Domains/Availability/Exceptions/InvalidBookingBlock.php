<?php

declare(strict_types=1);

namespace App\Domains\Availability\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBookingBlock extends DomainException implements DomainFailure
{
    public static function notPositive(int $minutes): self
    {
        return new self("A bookable block must last at least one minute, got [{$minutes}].");
    }

    public static function negativeBuffer(int $minutes): self
    {
        return new self("A booking buffer cannot be negative, got [{$minutes}].");
    }

    public function errorCode(): string
    {
        return 'invalid_booking_block';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
