<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CalendarAuthorizationStateInvalid extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('The calendar authorization carried no state.');
    }

    public static function expiredOrUsed(): self
    {
        return new self('The calendar authorization state has expired or was already used.');
    }

    public static function issuedToAnotherAccount(): self
    {
        return new self('The calendar authorization state was issued to another account.');
    }

    public function errorCode(): string
    {
        return 'calendar_authorization_state_invalid';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
