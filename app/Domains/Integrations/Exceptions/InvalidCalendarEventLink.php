<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidCalendarEventLink extends DomainException implements DomainFailure
{
    public static function missingEvent(): self
    {
        return new self('A calendar event link needs the external event it points to.');
    }

    public function errorCode(): string
    {
        return 'invalid_calendar_event_link';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
