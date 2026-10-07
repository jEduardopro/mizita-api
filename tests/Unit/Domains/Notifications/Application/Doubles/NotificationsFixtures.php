<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\ValueObjects\NotificationRecipient;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use App\Domains\Notifications\ValueObjects\NotificationType;
use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use App\Domains\Notifications\ValueObjects\NotifiedCustomer;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class NotificationsFixtures
{
    public const BUSINESS_ID = FakeBusinessContext::BUSINESS_ID;

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const MEMBER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const OWNER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a2';

    public const OTHER_MEMBER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a3';

    public const STRANGER_ACCOUNT_ID = '01930000-0000-7000-8000-0000000000a4';

    public const MEMBER_ID = '01930000-0000-7000-8000-0000000000d1';

    public const OWNER_MEMBER_ID = '01930000-0000-7000-8000-0000000000d2';

    public const OTHER_MEMBER_ID = '01930000-0000-7000-8000-0000000000d3';

    public const NOTIFICATION_ID = '01930000-0000-7000-8000-000000000201';

    public const SECOND_NOTIFICATION_ID = '01930000-0000-7000-8000-000000000202';

    public const THIRD_NOTIFICATION_ID = '01930000-0000-7000-8000-000000000203';

    public const UNKNOWN_NOTIFICATION_ID = '01930000-0000-7000-8000-0000000002ff';

    public const NEW_NOTIFICATION_ID = '01930000-0000-7000-8000-000000000299';

    public const APPOINTMENT_ID = '01930000-0000-7000-8000-000000000101';

    public const SECOND_APPOINTMENT_ID = '01930000-0000-7000-8000-000000000102';

    public const UNKNOWN_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000001ff';

    public const CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c1';

    public const MEMBER_NAME = 'Ana Muñoz';

    public const CUSTOMER_NAME = 'José Ñúñez';

    public const SERVICE_NAME = 'Corte y peinado';

    public const REFERENCE_CODE = 'AB12CD';

    public const NOW = '2026-03-10T09:30:00+00:00';

    public const CREATED_AT = '2026-03-10T08:00:00+00:00';

    public const READ_AT = '2026-03-10T08:15:00+00:00';

    public const STARTS_AT = '2026-03-12T10:00:00+00:00';

    public const ENDS_AT = '2026-03-12T10:45:00+00:00';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function notification(
        string $id = self::NOTIFICATION_ID,
        string $businessId = self::BUSINESS_ID,
        string $recipientStaffMemberId = self::MEMBER_ID,
        ?string $appointmentId = self::APPOINTMENT_ID,
        ?string $readAt = null,
    ): StaffNotification {
        return StaffNotification::restore(
            id: $id,
            businessId: $businessId,
            recipientStaffMemberId: $recipientStaffMemberId,
            type: NotificationType::AppointmentBooked,
            appointmentId: $appointmentId,
            readAt: $readAt === null ? null : new DateTimeImmutable($readAt),
            createdAt: new DateTimeImmutable(self::CREATED_AT),
        );
    }

    public static function record(
        string $id = self::NOTIFICATION_ID,
        string $recipientStaffMemberId = self::MEMBER_ID,
        string $recipientName = self::MEMBER_NAME,
        ?string $readAt = null,
        bool $withAppointment = true,
        bool $withCustomer = true,
    ): NotificationRecord {
        return new NotificationRecord(
            id: $id,
            type: NotificationType::AppointmentBooked,
            recipient: new NotificationRecipient($recipientStaffMemberId, $recipientName),
            appointment: $withAppointment ? self::appointment() : null,
            customer: $withCustomer ? new NotifiedCustomer(self::CUSTOMER_ID, self::CUSTOMER_NAME) : null,
            readAt: $readAt === null ? null : new DateTimeImmutable($readAt),
            createdAt: new DateTimeImmutable(self::CREATED_AT),
        );
    }

    public static function appointment(): NotifiedAppointment
    {
        return new NotifiedAppointment(
            appointmentId: self::APPOINTMENT_ID,
            startsAt: new DateTimeImmutable(self::STARTS_AT),
            endsAt: new DateTimeImmutable(self::ENDS_AT),
            serviceName: self::SERVICE_NAME,
            referenceCode: self::REFERENCE_CODE,
        );
    }
}
