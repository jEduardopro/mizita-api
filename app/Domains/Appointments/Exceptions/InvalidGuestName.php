<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class InvalidGuestName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A booking needs the name of the person attending.');
    }

    public static function tooLong(int $maximumLength): self
    {
        return new self("A name is at most {$maximumLength} characters long.");
    }

    public static function rejected(Throwable $previous): self
    {
        return new self('That name was not accepted.', 0, $previous);
    }

    public function errorCode(): string
    {
        return 'invalid_guest_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
