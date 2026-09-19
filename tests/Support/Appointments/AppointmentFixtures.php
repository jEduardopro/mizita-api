<?php

declare(strict_types=1);

namespace Tests\Support\Appointments;

use App\Domains\Appointments\Application\Dtos\BookAppointmentAsGuestInput;
use App\Domains\Appointments\Application\Dtos\CancelAppointmentInput;
use App\Domains\Appointments\Application\Dtos\CancelGuestBookingInput;
use App\Domains\Appointments\Application\Dtos\CreateAppointmentInput;
use App\Domains\Appointments\Application\Dtos\DeleteAppointmentInput;
use App\Domains\Appointments\Application\Dtos\GuestBookingCredentials;
use App\Domains\Appointments\Application\Dtos\GuestDetailsInput;
use App\Domains\Appointments\Application\Dtos\ListAppointmentsInput;
use App\Domains\Appointments\Application\Dtos\RescheduleGuestBookingInput;
use App\Domains\Appointments\Application\Dtos\ShowAppointmentInput;
use App\Domains\Appointments\Application\Dtos\ShowGuestBookingInput;
use App\Domains\Appointments\Application\Dtos\UpdateAppointmentInput;
use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\BookingSource;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\ManageToken;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;
use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;
use DateTimeImmutable;
use Tests\Support\FakeBusinessContext;

final class AppointmentFixtures
{
    public const NOW = '2026-01-01T12:00:00+00:00';

    public const APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000a1';

    public const SECOND_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000a2';

    public const GENERATED_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000a9';

    public const FOREIGN_APPOINTMENT_ID = '01930000-0000-7000-8000-0000000000af';

    public const OTHER_BUSINESS_ID = '01930000-0000-7000-8000-0000000000b2';

    public const CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c1';

    public const SECOND_CUSTOMER_ID = '01930000-0000-7000-8000-0000000000c2';

    public const SERVICE_ID = '01930000-0000-7000-8000-0000000000e1';

    public const SECOND_SERVICE_ID = '01930000-0000-7000-8000-0000000000e2';

    public const STAFF_ID = '01930000-0000-7000-8000-0000000000d1';

    public const SECOND_STAFF_ID = '01930000-0000-7000-8000-0000000000d2';

    public const UNKNOWN_ID = '01930000-0000-7000-8000-00000000ffff';

    public const CUSTOMER_NAME = 'Ada Lovelace';

    public const CUSTOMER_EMAIL = 'ada@example.com';

    public const SECOND_CUSTOMER_NAME = 'Grace Hopper';

    public const SERVICE_NAME = 'Corte de pelo';

    public const SERVICE_COLOR = '#0EA5A4';

    public const SERVICE_DURATION_MINUTES = 45;

    public const SECOND_SERVICE_NAME = 'Barba';

    public const SECOND_SERVICE_COLOR = '#F97316';

    public const SECOND_SERVICE_DURATION_MINUTES = 30;

    public const STAFF_NAME = 'Katherine Johnson';

    public const SECOND_STAFF_NAME = 'Dorothy Vaughan';

    public const STARTS_AT = '2026-03-10T09:00:00+00:00';

    public const ENDS_AT = '2026-03-10T10:30:00+00:00';

    public const DERIVED_ENDS_AT = '2026-03-10T09:45:00+00:00';

    public const RANGE_FROM = '2026-03-01T00:00:00+00:00';

    public const RANGE_TO = '2026-03-31T00:00:00+00:00';

    public const NOTES = 'Prefiere cita por la mañana.';

    public const REFERENCE_CODE = 'A2B3C4D5';

    public const MANAGE_TOKEN = 'a1b2c3d4e5f6071829304a5b6c7d8e9fa1b2c3d4e5f6071829304a5b6c7d8e9f';

    public const OTHER_MANAGE_TOKEN = 'f9e8d7c6b5a4039281706f5e4d3c2b1af9e8d7c6b5a4039281706f5e4d3c2b1a';

    public const MANAGE_TOKEN_EXPIRES_AT = '2026-03-17T09:00:00+00:00';

    public const UNKNOWN_REFERENCE_CODE = 'Z9Y8X7W6';

    public const RESCHEDULED_STARTS_AT = '2026-03-12T11:00:00+00:00';

    public const GUEST_NAME = 'Ada Lovelace';

    public const GUEST_EMAIL = 'ada@example.com';

    public static function now(): DateTimeImmutable
    {
        return self::instant(self::NOW);
    }

    public static function instant(string $value): DateTimeImmutable
    {
        return new DateTimeImmutable($value);
    }

    public static function customerSnapshot(
        string $id = self::CUSTOMER_ID,
        string $name = self::CUSTOMER_NAME,
        ?string $email = self::CUSTOMER_EMAIL,
    ): CustomerSnapshot {
        return new CustomerSnapshot($id, $name, $email);
    }

    public static function serviceSnapshot(
        string $id = self::SERVICE_ID,
        string $name = self::SERVICE_NAME,
        string $color = self::SERVICE_COLOR,
        int $durationMinutes = self::SERVICE_DURATION_MINUTES,
        bool $active = true,
    ): ServiceSnapshot {
        return new ServiceSnapshot($id, $name, $color, $durationMinutes, $active);
    }

    public static function staffSnapshot(
        string $id = self::STAFF_ID,
        string $name = self::STAFF_NAME,
    ): StaffMemberSnapshot {
        return new StaffMemberSnapshot($id, $name);
    }

    public static function appointment(
        string $id = self::APPOINTMENT_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        string $customerId = self::CUSTOMER_ID,
        string $serviceId = self::SERVICE_ID,
        string $staffMemberId = self::STAFF_ID,
        string $startsAt = self::STARTS_AT,
        string $endsAt = self::ENDS_AT,
        ?string $notes = self::NOTES,
        ?DateTimeImmutable $createdAt = null,
        ?string $referenceCode = null,
        ?string $manageTokenHash = null,
        ?string $manageTokenExpiresAt = null,
        ?string $cancelledAt = null,
        ?Canceller $cancelledBy = null,
        BookingSource $source = BookingSource::Admin,
    ): Appointment {
        return Appointment::restore(
            id: $id,
            businessId: $businessId,
            customerId: $customerId,
            serviceId: $serviceId,
            staffMemberId: $staffMemberId,
            slot: AppointmentSlot::restore(self::instant($startsAt), self::instant($endsAt)),
            notes: $notes === null ? null : AppointmentNotes::restore($notes),
            createdAt: $createdAt ?? self::now(),
            referenceCode: $referenceCode === null ? null : ReferenceCode::restore($referenceCode),
            manageTokenHash: $manageTokenHash,
            manageTokenExpiresAt: $manageTokenExpiresAt === null ? null : self::instant($manageTokenExpiresAt),
            cancelledAt: $cancelledAt === null ? null : self::instant($cancelledAt),
            cancelledBy: $cancelledBy,
            source: $source,
        );
    }

    public static function guestAppointment(
        string $id = self::APPOINTMENT_ID,
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        string $startsAt = self::STARTS_AT,
        string $endsAt = self::ENDS_AT,
        string $referenceCode = self::REFERENCE_CODE,
        string $manageToken = self::MANAGE_TOKEN,
        string $manageTokenExpiresAt = self::MANAGE_TOKEN_EXPIRES_AT,
        ?DateTimeImmutable $createdAt = null,
    ): Appointment {
        return Appointment::bookAsGuest(
            id: $id,
            businessId: $businessId,
            customerId: self::CUSTOMER_ID,
            serviceId: self::SERVICE_ID,
            staffMemberId: self::STAFF_ID,
            slot: AppointmentSlot::restore(self::instant($startsAt), self::instant($endsAt)),
            notes: null,
            referenceCode: ReferenceCode::fromString($referenceCode),
            manageTokenHash: ManageToken::fromString($manageToken)->hash(),
            manageTokenExpiresAt: self::instant($manageTokenExpiresAt),
            now: $createdAt ?? self::now(),
        );
    }

    public static function createInput(
        string $customerId = self::CUSTOMER_ID,
        string $serviceId = self::SERVICE_ID,
        string $staffMemberId = self::STAFF_ID,
        string $startsAt = self::STARTS_AT,
        ?string $endsAt = self::ENDS_AT,
        ?string $notes = self::NOTES,
    ): CreateAppointmentInput {
        return new CreateAppointmentInput(
            customerId: $customerId,
            serviceId: $serviceId,
            staffMemberId: $staffMemberId,
            startsAt: $startsAt,
            endsAt: $endsAt,
            notes: $notes,
        );
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function createPayload(array $extra = []): array
    {
        return [
            'customer_id' => self::CUSTOMER_ID,
            'service_id' => self::SERVICE_ID,
            'staff_member_id' => self::STAFF_ID,
            'starts_at' => self::STARTS_AT,
            'ends_at' => self::ENDS_AT,
            'notes' => self::NOTES,
            ...$extra,
        ];
    }

    public static function updateInput(
        string $appointmentId = self::APPOINTMENT_ID,
        string $customerId = self::CUSTOMER_ID,
        string $serviceId = self::SERVICE_ID,
        string $staffMemberId = self::STAFF_ID,
        string $startsAt = self::STARTS_AT,
        ?string $endsAt = self::ENDS_AT,
        ?string $notes = self::NOTES,
    ): UpdateAppointmentInput {
        return new UpdateAppointmentInput(
            appointmentId: $appointmentId,
            customerId: $customerId,
            serviceId: $serviceId,
            staffMemberId: $staffMemberId,
            startsAt: $startsAt,
            endsAt: $endsAt,
            notes: $notes,
        );
    }

    public static function listInput(
        string $from = self::RANGE_FROM,
        string $to = self::RANGE_TO,
    ): ListAppointmentsInput {
        return new ListAppointmentsInput(from: $from, to: $to);
    }

    public static function showInput(string $appointmentId = self::APPOINTMENT_ID): ShowAppointmentInput
    {
        return new ShowAppointmentInput(appointmentId: $appointmentId);
    }

    public static function deleteInput(string $appointmentId = self::APPOINTMENT_ID): DeleteAppointmentInput
    {
        return new DeleteAppointmentInput(appointmentId: $appointmentId);
    }

    public static function cancelInput(string $appointmentId = self::APPOINTMENT_ID): CancelAppointmentInput
    {
        return new CancelAppointmentInput(appointmentId: $appointmentId);
    }

    public static function guestDetails(
        string $name = self::GUEST_NAME,
        ?string $email = self::GUEST_EMAIL,
        ?string $phoneCountryCode = null,
        ?string $phoneNationalNumber = null,
    ): GuestDetailsInput {
        return new GuestDetailsInput(
            name: $name,
            email: $email,
            phoneCountryCode: $phoneCountryCode,
            phoneNationalNumber: $phoneNationalNumber,
        );
    }

    public static function bookAsGuestInput(
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        string $serviceId = self::SERVICE_ID,
        string $staffMemberId = self::STAFF_ID,
        string $startsAt = self::STARTS_AT,
        ?GuestDetailsInput $guest = null,
        ?string $notes = null,
    ): BookAppointmentAsGuestInput {
        return new BookAppointmentAsGuestInput(
            businessId: $businessId,
            serviceId: $serviceId,
            staffMemberId: $staffMemberId,
            startsAt: $startsAt,
            guest: $guest ?? self::guestDetails(),
            notes: $notes,
        );
    }

    public static function credentials(
        string $referenceCode = self::REFERENCE_CODE,
        string $manageToken = self::MANAGE_TOKEN,
    ): GuestBookingCredentials {
        return new GuestBookingCredentials(referenceCode: $referenceCode, manageToken: $manageToken);
    }

    public static function showGuestInput(
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        ?GuestBookingCredentials $credentials = null,
    ): ShowGuestBookingInput {
        return new ShowGuestBookingInput(
            businessId: $businessId,
            credentials: $credentials ?? self::credentials(),
        );
    }

    public static function rescheduleGuestInput(
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        ?GuestBookingCredentials $credentials = null,
        string $startsAt = self::RESCHEDULED_STARTS_AT,
    ): RescheduleGuestBookingInput {
        return new RescheduleGuestBookingInput(
            businessId: $businessId,
            credentials: $credentials ?? self::credentials(),
            startsAt: $startsAt,
        );
    }

    public static function cancelGuestInput(
        string $businessId = FakeBusinessContext::BUSINESS_ID,
        ?GuestBookingCredentials $credentials = null,
    ): CancelGuestBookingInput {
        return new CancelGuestBookingInput(
            businessId: $businessId,
            credentials: $credentials ?? self::credentials(),
        );
    }
}
