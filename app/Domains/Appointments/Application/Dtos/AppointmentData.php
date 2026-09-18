<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;
use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;
use DateTimeImmutable;

final readonly class AppointmentData
{
    public function __construct(
        public string $id,
        public AppointmentCustomerData $customer,
        public AppointmentServiceData $service,
        public AppointmentStaffData $staffMember,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $durationMinutes,
        public ?string $notes,
        public DateTimeImmutable $createdAt,
    ) {}

    public static function fromEntity(
        Appointment $appointment,
        CustomerSnapshot $customer,
        ServiceSnapshot $service,
        StaffMemberSnapshot $staffMember,
    ): self {
        return new self(
            id: $appointment->id,
            customer: AppointmentCustomerData::fromSnapshot($customer),
            service: AppointmentServiceData::fromSnapshot($service),
            staffMember: AppointmentStaffData::fromSnapshot($staffMember),
            startsAt: $appointment->slot()->startsAt,
            endsAt: $appointment->slot()->endsAt,
            durationMinutes: $appointment->slot()->durationMinutes(),
            notes: $appointment->notes()?->value,
            createdAt: $appointment->createdAt,
        );
    }
}
