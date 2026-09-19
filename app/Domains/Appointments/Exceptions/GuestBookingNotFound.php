<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class GuestBookingNotFound extends RuntimeException implements DomainFailure
{
    private const MESSAGE = 'No booking matches that reservation code and management link.';

    public static function forCredentials(?Throwable $previous = null): self
    {
        return new self(self::MESSAGE, 0, $previous);
    }

    public function errorCode(): string
    {
        return 'guest_booking_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
