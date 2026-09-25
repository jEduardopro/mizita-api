<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Infrastructure\Support;

use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;
use App\Domains\Integrations\ValueObjects\CalendarEventDraft;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class IntegrationsFixtures
{
    public const NOW = '2026-03-29T10:00:00+00:00';

    public const CONNECTION_ID = '01930000-0000-7000-8000-0000000000c1';

    public const OTHER_CONNECTION_ID = '01930000-0000-7000-8000-0000000000c2';

    public const STAFF_MEMBER_ID = '01930000-0000-7000-8000-0000000000d1';

    public const APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000e1';

    public const LINK_ID = '01930000-0000-7000-8000-0000000000f1';

    public const ACCOUNT_EMAIL = 'ada@example.com';

    public const CALENDAR_ID = 'mizita-ada@group.calendar.google.com';

    public const ENCODED_CALENDAR_ID = 'mizita-ada%40group.calendar.google.com';

    public const ACCESS_TOKEN = 'ya29.stored-access-token-secret';

    public const REFRESHED_ACCESS_TOKEN = 'ya29.refreshed-access-token-secret';

    public const REFRESH_TOKEN = '1//stored-refresh-token-secret';

    public const GRANT_ACCESS_TOKEN = 'ya29.granted-access-token-secret';

    public const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';

    public const BUSINESS_NAME = 'Barbería Ñandú';

    public const TIMEZONE = 'Europe/Madrid';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function connection(
        string $id = self::CONNECTION_ID,
        string $externalCalendarId = self::CALENDAR_ID,
        ConnectionStatus $status = ConnectionStatus::Connected,
    ): CalendarConnection {
        return CalendarConnection::restore(
            id: $id,
            businessId: FakeBusinessContext::BUSINESS_ID,
            staffMemberId: self::STAFF_MEMBER_ID,
            provider: CalendarProvider::Google,
            accountEmail: self::ACCOUNT_EMAIL,
            externalCalendarId: $externalCalendarId,
            status: $status,
            connectedAt: self::now(),
        );
    }

    public static function draft(
        string $title = 'Corte de pelo · Ada Lovelace',
        string $description = "Referencia MZ-7Q2K\nhttps://mizita.test/calendar",
    ): CalendarEventDraft {
        return new CalendarEventDraft(
            appointmentId: self::APPOINTMENT_ID,
            title: $title,
            description: $description,
            startsAt: new DateTimeImmutable('2026-03-29T08:30:00+00:00'),
            endsAt: new DateTimeImmutable('2026-03-29T09:15:00+00:00'),
            timezone: self::TIMEZONE,
        );
    }

    public static function grant(): CalendarGrant
    {
        return new CalendarGrant(
            accountEmail: self::ACCOUNT_EMAIL,
            tokens: new CalendarTokens(
                accessToken: self::GRANT_ACCESS_TOKEN,
                refreshToken: self::REFRESH_TOKEN,
                accessTokenExpiresAt: new DateTimeImmutable('2026-03-29T11:00:00+00:00'),
            ),
        );
    }

    public static function business(): BusinessCalendarProfile
    {
        return new BusinessCalendarProfile(self::BUSINESS_NAME, self::TIMEZONE);
    }
}
