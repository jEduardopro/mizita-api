<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Integrations\Application\Doubles;

use App\Domains\Integrations\Application\Dtos\BusyIntervalData;
use App\Domains\Integrations\Application\Dtos\CompleteCalendarAuthorizationInput;
use App\Domains\Integrations\Application\Dtos\ListCalendarBusyIntervalsInput;
use App\Domains\Integrations\Entities\CalendarConnection;
use App\Domains\Integrations\Entities\CalendarEventLink;
use App\Domains\Integrations\ValueObjects\AllDayEventSpan;
use App\Domains\Integrations\ValueObjects\AppointmentLifecycle;
use App\Domains\Integrations\ValueObjects\AppointmentSnapshot;
use App\Domains\Integrations\ValueObjects\BusinessCalendarProfile;
use App\Domains\Integrations\ValueObjects\CalendarGrant;
use App\Domains\Integrations\ValueObjects\CalendarProvider;
use App\Domains\Integrations\ValueObjects\CalendarTokens;
use App\Domains\Integrations\ValueObjects\ConnectionStatus;
use App\Domains\Integrations\ValueObjects\ExternalCalendarEvent;
use App\Domains\Integrations\ValueObjects\ExternalEventAvailability;
use App\Domains\Integrations\ValueObjects\ExternalEventOrigin;
use App\Domains\Integrations\ValueObjects\PendingAuthorization;
use App\Domains\Integrations\ValueObjects\TimedEventSpan;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class IntegrationsFixtures
{
    public const BUSINESS_ID = FakeBusinessContext::BUSINESS_ID;

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const OTHER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a2';

    public const STAFF_MEMBER_ID = '01930000-0000-7000-8000-0000000000d1';

    public const SECOND_STAFF_MEMBER_ID = '01930000-0000-7000-8000-0000000000d2';

    public const CONNECTION_ID = '01930000-0000-7000-8000-0000000000e1';

    public const SECOND_CONNECTION_ID = '01930000-0000-7000-8000-0000000000e2';

    public const NEW_CONNECTION_ID = '01930000-0000-7000-8000-0000000000e3';

    public const LINK_ID = '01930000-0000-7000-8000-0000000000f1';

    public const SECOND_LINK_ID = '01930000-0000-7000-8000-0000000000f2';

    public const NEW_LINK_ID = '01930000-0000-7000-8000-0000000000f3';

    public const APPOINTMENT_ID = '01930000-0000-7000-8000-000000000101';

    public const SECOND_APPOINTMENT_ID = '01930000-0000-7000-8000-000000000102';

    public const THIRD_APPOINTMENT_ID = '01930000-0000-7000-8000-000000000103';

    public const ACCOUNT_EMAIL = 'ada@example.com';

    public const NEW_ACCOUNT_EMAIL = 'ada.lovelace@example.com';

    public const EXTERNAL_CALENDAR_ID = 'mizita-studio-ada@group.calendar.google.com';

    public const NEW_EXTERNAL_CALENDAR_ID = 'mizita-studio-ada-2@group.calendar.google.com';

    public const EXTERNAL_EVENT_ID = 'mizitaevent0001';

    public const SECOND_EXTERNAL_EVENT_ID = 'mizitaevent0002';

    public const STATE = 'aW50ZWdyYXRpb25zLXN0YXRlLXRva2Vu';

    public const CODE = '4/0AbCdEfGhIjKlMnOp';

    public const NOW = '2026-09-25T10:00:00+00:00';

    public const CONNECTED_AT = '2026-09-01T08:00:00+00:00';

    public const STARTS_AT = '2026-10-02T09:00:00+00:00';

    public const ENDS_AT = '2026-10-02T09:45:00+00:00';

    public const MIZITA_LINK = 'https://mizita.test/dashboard/appointments';

    public const BUSINESS_NAME = 'Studio Ada';

    public const BUSINESS_TIMEZONE = 'Europe/Madrid';

    public static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    public static function now(): DateTimeImmutable
    {
        return self::instant(self::NOW);
    }

    public static function connection(
        string $id = self::CONNECTION_ID,
        string $businessId = self::BUSINESS_ID,
        string $staffMemberId = self::STAFF_MEMBER_ID,
        ConnectionStatus $status = ConnectionStatus::Connected,
        string $accountEmail = self::ACCOUNT_EMAIL,
        string $externalCalendarId = self::EXTERNAL_CALENDAR_ID,
        string $connectedAt = self::CONNECTED_AT,
    ): CalendarConnection {
        return CalendarConnection::restore(
            id: $id,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            provider: CalendarProvider::Google,
            accountEmail: $accountEmail,
            externalCalendarId: $externalCalendarId,
            status: $status,
            connectedAt: self::instant($connectedAt),
        );
    }

    public static function awaitingReconnect(
        string $id = self::CONNECTION_ID,
        string $staffMemberId = self::STAFF_MEMBER_ID,
    ): CalendarConnection {
        return self::connection(id: $id, staffMemberId: $staffMemberId, status: ConnectionStatus::NeedsReconnect);
    }

    public static function link(
        string $id = self::LINK_ID,
        string $connectionId = self::CONNECTION_ID,
        string $appointmentId = self::APPOINTMENT_ID,
        string $externalEventId = self::EXTERNAL_EVENT_ID,
        string $businessId = self::BUSINESS_ID,
    ): CalendarEventLink {
        return CalendarEventLink::restore($id, $businessId, $connectionId, $appointmentId, $externalEventId);
    }

    public static function snapshot(
        string $staffMemberId = self::STAFF_MEMBER_ID,
        AppointmentLifecycle $lifecycle = AppointmentLifecycle::Active,
        string $appointmentId = self::APPOINTMENT_ID,
        string $businessId = self::BUSINESS_ID,
    ): AppointmentSnapshot {
        return new AppointmentSnapshot(
            appointmentId: $appointmentId,
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            startsAt: self::instant(self::STARTS_AT),
            endsAt: self::instant(self::ENDS_AT),
            serviceName: 'Corte de pelo',
            customerName: 'Begoña Muñoz',
            referenceCode: 'MZ-7Q4K',
            timezone: self::BUSINESS_TIMEZONE,
            lifecycle: $lifecycle,
        );
    }

    public static function tokens(): CalendarTokens
    {
        return new CalendarTokens(
            accessToken: 'ya29.access-token',
            refreshToken: '1//refresh-token',
            accessTokenExpiresAt: self::instant('2026-09-25T11:00:00+00:00'),
        );
    }

    public static function grant(string $accountEmail = self::ACCOUNT_EMAIL): CalendarGrant
    {
        return new CalendarGrant($accountEmail, self::tokens());
    }

    public static function profile(string $timezone = self::BUSINESS_TIMEZONE): BusinessCalendarProfile
    {
        return new BusinessCalendarProfile(self::BUSINESS_NAME, $timezone);
    }

    public static function pending(
        string $accountId = self::ACCOUNT_ID,
        string $businessId = self::BUSINESS_ID,
        string $staffMemberId = self::STAFF_MEMBER_ID,
    ): PendingAuthorization {
        return new PendingAuthorization($accountId, $businessId, $staffMemberId);
    }

    public static function completeInput(
        string $state = self::STATE,
        string $code = self::CODE,
        string $error = '',
        string $accountId = self::ACCOUNT_ID,
    ): CompleteCalendarAuthorizationInput {
        return new CompleteCalendarAuthorizationInput($state, $code, $error, $accountId);
    }

    public static function busyInput(
        string $from = '2026-10-01T00:00:00+00:00',
        string $to = '2026-10-08T00:00:00+00:00',
        string $businessId = self::BUSINESS_ID,
        string $staffMemberId = self::STAFF_MEMBER_ID,
    ): ListCalendarBusyIntervalsInput {
        return new ListCalendarBusyIntervalsInput($businessId, $staffMemberId, self::instant($from), self::instant($to));
    }

    public static function externalEvent(
        string $startsAt,
        string $endsAt,
        ExternalEventAvailability $availability = ExternalEventAvailability::Busy,
        ExternalEventOrigin $origin = ExternalEventOrigin::AddedByHand,
    ): ExternalCalendarEvent {
        return new ExternalCalendarEvent(
            new TimedEventSpan(self::instant($startsAt), self::instant($endsAt)),
            $origin,
            $availability,
        );
    }

    public static function allDayEvent(string $firstDate, string $dayAfterLastDate): ExternalCalendarEvent
    {
        return new ExternalCalendarEvent(
            new AllDayEventSpan($firstDate, $dayAfterLastDate),
            ExternalEventOrigin::AddedByHand,
            ExternalEventAvailability::Busy,
        );
    }

    /**
     * @param  list<BusyIntervalData>  $intervals
     * @return list<array{0: string, 1: string}>
     */
    public static function atomsOf(array $intervals): array
    {
        return array_map(
            static fn (BusyIntervalData $interval): array => [
                $interval->startsAt->format(DATE_ATOM),
                $interval->endsAt->format(DATE_ATOM),
            ],
            $intervals,
        );
    }
}
