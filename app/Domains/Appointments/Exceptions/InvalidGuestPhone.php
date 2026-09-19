<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class InvalidGuestPhone extends DomainException implements DomainFailure
{
    public static function malformed(): self
    {
        return new self('That phone number cannot be dialled.');
    }

    public static function rejected(Throwable $previous): self
    {
        return new self('That phone number was not accepted.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'invalid_guest_phone';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
