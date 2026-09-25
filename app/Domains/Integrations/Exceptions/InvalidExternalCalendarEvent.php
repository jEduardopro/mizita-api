<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidExternalCalendarEvent extends DomainException implements DomainFailure
{
    public static function endsBeforeItStarts(): self
    {
        return new self('An external calendar event must end after it starts.');
    }

    public static function malformedDate(string $date): self
    {
        return new self("The external calendar event date [{$date}] is not a calendar date.");
    }

    public function errorCode(): string
    {
        return 'invalid_external_calendar_event';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
