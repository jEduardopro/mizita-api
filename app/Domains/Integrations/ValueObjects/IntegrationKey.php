<?php

declare(strict_types=1);

namespace App\Domains\Integrations\ValueObjects;

enum IntegrationKey: string
{
    case GoogleCalendar = 'google_calendar';

    public function category(): IntegrationCategory
    {
        return match ($this) {
            self::GoogleCalendar => IntegrationCategory::CalendarSync,
        };
    }

    public function calendarProvider(): CalendarProvider
    {
        return match ($this) {
            self::GoogleCalendar => CalendarProvider::Google,
        };
    }
}
