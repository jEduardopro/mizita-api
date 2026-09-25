<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCalendarConnection extends DomainException implements DomainFailure
{
    public static function missingAccountEmail(): self
    {
        return new self('A calendar connection needs the email of the connected account.');
    }

    public static function missingCalendar(): self
    {
        return new self('A calendar connection needs the calendar it publishes to.');
    }

    public function errorCode(): string
    {
        return 'invalid_calendar_connection';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
