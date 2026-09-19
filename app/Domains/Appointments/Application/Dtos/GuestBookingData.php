<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Entities\Appointment;
use App\Domains\Appointments\ValueObjects\AppointmentStatus;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\Appointments\ValueObjects\CustomerSnapshot;
use App\Domains\Appointments\ValueObjects\ServiceSnapshot;
use App\Domains\Appointments\ValueObjects\StaffMemberSnapshot;
use DateTimeImmutable;

final readonly class GuestBookingData
{
    public function __construct(
        public string $referenceCode,
        public string $customerName,
        public string $serviceName,
        public string $staffMemberName,
        public DateTimeImmutable $startsAt,
        public DateTimeImmutable $endsAt,
        public int $durationMinutes,
        public AppointmentStatus $status,
        public ?DateTimeImmutable $cancelledAt,
        public ?int $cancellationWindowMinutes,
        public bool $changeable,
    ) {}

    public static function fromEntity(
        Appointment $appointment,
        string $referenceCode,
        CustomerSnapshot $customer,
        ServiceSnapshot $service,
        StaffMemberSnapshot $staffMember,
        CancellationRule $rule,
        DateTimeImmutable $now,
    ): self {
        return new self(
            referenceCode: $referenceCode,
            customerName: $customer->name,
            serviceName: $service->name,
            staffMemberName: $staffMember->name,
            startsAt: $appointment->slot()->startsAt,
            endsAt: $appointment->slot()->endsAt,
            durationMinutes: $appointment->slot()->durationMinutes(),
            status: self::statusOf($appointment),
            cancelledAt: $appointment->cancelledAt(),
            cancellationWindowMinutes: $rule->minutes,
            changeable: $appointment->allowsChangeUnder($rule, $now),
        );
    }

    private static function statusOf(Appointment $appointment): AppointmentStatus
    {
        return $appointment->isCancelled()
            ? AppointmentStatus::Cancelled
            : AppointmentStatus::Booked;
    }
}
