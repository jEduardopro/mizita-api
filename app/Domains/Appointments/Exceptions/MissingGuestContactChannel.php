<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class MissingGuestContactChannel extends DomainException implements DomainFailure
{
    private const MESSAGE = 'A booking needs either an email address or a phone number.';

    public static function forGuest(?Throwable $previous = null): self
    {
        return new self(self::MESSAGE, 0, $previous);
    }

    public function errorCode(): string
    {
        return 'missing_guest_contact_channel';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
