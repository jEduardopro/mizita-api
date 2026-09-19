<?php

declare(strict_types=1);

namespace App\Domains\Customers\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidGuestContact extends DomainException implements DomainFailure
{
    public static function withoutEmailOrPhone(): self
    {
        return new self('A guest must provide an email address or a phone number.');
    }

    public function errorCode(): string
    {
        return 'invalid_guest_contact';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
