<?php

declare(strict_types=1);

namespace Tests\Unit\Domains\Notifications\Application\Doubles;

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\ValueObjects\BookedAppointment;
use App\Domains\Notifications\ValueObjects\NotificationRecipient;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use App\Domains\Notifications\ValueObjects\NotifiedAppointment;
use App\Domains\Notifications\ValueObjects\NotifiedCustomer;
use App\Domains\Notifications\ValueObjects\NotifiedStaffMember;
use App\Domains\Notifications\ValueObjects\Payloads\AppointmentBookedPayload;
use App\Domains\Notifications\ValueObjects\Payloads\StaffScheduleChangedPayload;
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

    public const NEXT_NOTIFICATION_ID = '01930000-0000-7000-8000-000000000298';

    public const EVENT_ID = '01930000-0000-7000-8000-000000000301';

    public const NEXT_EVENT_ID = '01930000-0000-7000-8000-000000000302';

    public const APPOINTMENT_ID = '01930000-0000-7000-8000-000000000101';

    public const SECOND_APPOINTMENT_ID = '01930000-0000-7000-8000-000000000102';

    public const UNKNOWN_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000001ff';

    public const CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c1';

    public const MEMBER_NAME = 'Ana Muñoz';

    public const OWNER_NAME = 'Begoña Ibáñez';

    public const OTHER_MEMBER_NAME = 'Íñigo Peña';

    public const CUSTOMER_NAME = 'José Ñúñez';

    public const SERVICE_NAME = 'Corte y peinado';

    public const REFERENCE_CODE = 'AB12CD';

    public const NOW = '2026-03-10T09:30:00+00:00';

    public const CREATED_AT = '2026-03-10T08:00:00+00:00';

    public const READ_AT = '2026-03-10T08:15:00+00:00';

    public const STARTS_AT = '2026-03-12T10:00:00+00:00';

    public const ENDS_AT = '2026-03-12T10:45:00+00:00';

    public const BOOKING_COLLAPSE_KEY = 'appointment_booked:'.self::APPOINTMENT_ID;

    public const SCHEDULE_CHANGE_COLLAPSE_KEY = 'staff_schedule_changed:'.self::MEMBER_ID;

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    public static function notification(
        string $id = self::NOTIFICATION_ID,
        string $businessId = self::BUSINESS_ID,
        string $recipientStaffMemberId = self::MEMBER_ID,
        string $eventId = self::EVENT_ID,
        string $collapseKey = self::BOOKING_COLLAPSE_KEY,
        ?string $readAt = null,
    ): StaffNotification {
        return StaffNotification::restore(
            id: $id,
            businessId: $businessId,
            eventId: $eventId,
            recipientStaffMemberId: $recipientStaffMemberId,
            collapseKey: $collapseKey,
            readAt: $readAt === null ? null : new DateTimeImmutable($readAt),
            createdAt: new DateTimeImmutable(self::CREATED_AT),
        );
    }

    public static function record(
        string $id = self::NOTIFICATION_ID,
        string $recipientStaffMemberId = self::MEMBER_ID,
        string $recipientName = self::MEMBER_NAME,
        ?string $readAt = null,
    ): NotificationRecord {
        return new NotificationRecord(
            id: $id,
            recipient: new NotificationRecipient($recipientStaffMemberId, $recipientName),
            payload: self::bookingPayload(),
            readAt: $readAt === null ? null : new DateTimeImmutable($readAt),
            createdAt: new DateTimeImmutable(self::CREATED_AT),
        );
    }

    public static function scheduleChangeRecord(?string $readAt = null): NotificationRecord
    {
        return new NotificationRecord(
            id: self::NOTIFICATION_ID,
            recipient: new NotificationRecipient(self::OWNER_MEMBER_ID, self::OWNER_NAME),
            payload: self::scheduleChangePayload(),
            readAt: $readAt === null ? null : new DateTimeImmutable($readAt),
            createdAt: new DateTimeImmutable(self::CREATED_AT),
        );
    }

    public static function bookingPayload(): AppointmentBookedPayload
    {
        return new AppointmentBookedPayload(self::appointment(), self::customer());
    }

    public static function scheduleChangePayload(
        string $staffMemberId = self::MEMBER_ID,
        string $name = self::MEMBER_NAME,
    ): StaffScheduleChangedPayload {
        return new StaffScheduleChangedPayload(new NotifiedStaffMember($staffMemberId, $name));
    }

    /**
     * @return array<string, array<string, string>>
     */
    public static function bookingSnapshot(): array
    {
        return [
            'appointment' => [
                'id' => self::APPOINTMENT_ID,
                'starts_at' => self::STARTS_AT,
                'ends_at' => self::ENDS_AT,
                'service_name' => self::SERVICE_NAME,
                'reference_code' => self::REFERENCE_CODE,
            ],
            'customer' => [
                'id' => self::CUSTOMER_ID,
                'name' => self::CUSTOMER_NAME,
            ],
        ];
    }

    public static function bookedAppointment(
        string $appointmentId = self::APPOINTMENT_ID,
        string $businessId = self::BUSINESS_ID,
        string $staffMemberId = self::MEMBER_ID,
    ): BookedAppointment {
        return new BookedAppointment(
            businessId: $businessId,
            staffMemberId: $staffMemberId,
            appointment: self::appointment($appointmentId),
            customer: self::customer(),
        );
    }

    public static function appointment(string $appointmentId = self::APPOINTMENT_ID): NotifiedAppointment
    {
        return new NotifiedAppointment(
            appointmentId: $appointmentId,
            startsAt: new DateTimeImmutable(self::STARTS_AT),
            endsAt: new DateTimeImmutable(self::ENDS_AT),
            serviceName: self::SERVICE_NAME,
            referenceCode: self::REFERENCE_CODE,
        );
    }

    public static function customer(): NotifiedCustomer
    {
        return new NotifiedCustomer(self::CUSTOMER_ID, self::CUSTOMER_NAME);
    }
}
