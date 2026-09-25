<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class CalendarAuthorizationDenied extends DomainException implements DomainFailure
{
    public static function byUser(): self
    {
        return new self('The calendar authorization was denied by the user.');
    }

    public function errorCode(): string
    {
        return 'calendar_authorization_denied';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
