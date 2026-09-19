<?php

declare(strict_types=1);

namespace App\Domains\BookingPolicies\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCancellationWindow extends DomainException implements DomainFailure
{
    public static function negative(int $minutes): self
    {
        return new self("[{$minutes}] is not a cancellation window a customer can act within.");
    }

    public static function tooLong(int $minutes): self
    {
        return new self("[{$minutes}] is further ahead than a cancellation window may reach.");
    }

    public function errorCode(): string
    {
        return 'invalid_cancellation_window';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
