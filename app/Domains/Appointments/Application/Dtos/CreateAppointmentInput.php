<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\AppointmentCustomerNotFound;
use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\Exceptions\InvalidAppointmentNotes;
use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Domains\Appointments\ValueObjects\Identifier;
use App\Domains\Appointments\ValueObjects\ScheduleInstant;
use DateTimeImmutable;

final readonly class CreateAppointmentInput
{
    public function __construct(
        public string $customerId,
        public string $serviceId,
        public string $staffMemberId,
        public string $startsAt,
        public ?string $endsAt,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload): self
    {
        return new self(
            customerId: self::textOrEmpty($payload['customer_id'] ?? null),
            serviceId: self::textOrEmpty($payload['service_id'] ?? null),
            staffMemberId: self::textOrEmpty($payload['staff_member_id'] ?? null),
            startsAt: self::textOrEmpty($payload['starts_at'] ?? null),
            endsAt: self::textOrNull($payload['ends_at'] ?? null),
            notes: self::textOrNull($payload['notes'] ?? null),
        );
    }

    /**
     * @throws AppointmentCustomerNotFound
     * @throws AppointmentServiceNotFound
     * @throws AppointmentStaffNotFound
     * @throws InvalidAppointmentSchedule
     * @throws InvalidAppointmentNotes
     */
    public function validate(): void
    {
        $this->validateCustomerId();
        $this->validateServiceId();
        $this->validateStaffMemberId();
        $this->validateSchedule();
        $this->validateNotes();
    }

    /**
     * @throws InvalidAppointmentSchedule
     */
    public function toStartsAt(): DateTimeImmutable
    {
        return ScheduleInstant::fromString($this->startsAt)->value;
    }

    /**
     * @throws InvalidAppointmentSchedule
     */
    public function toEndsAt(): ?DateTimeImmutable
    {
        return ScheduleInstant::fromNullable($this->endsAt)?->value;
    }

    /**
     * @throws InvalidAppointmentNotes
     */
    public function toNotes(): ?AppointmentNotes
    {
        return AppointmentNotes::fromNullable($this->notes);
    }

    private static function textOrEmpty(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private static function textOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }

    private function validateCustomerId(): void
    {
        if (! Identifier::isWellFormed($this->customerId)) {
            throw AppointmentCustomerNotFound::withId($this->customerId);
        }
    }

    private function validateServiceId(): void
    {
        if (! Identifier::isWellFormed($this->serviceId)) {
            throw AppointmentServiceNotFound::withId($this->serviceId);
        }
    }

    private function validateStaffMemberId(): void
    {
        if (! Identifier::isWellFormed($this->staffMemberId)) {
            throw AppointmentStaffNotFound::withId($this->staffMemberId);
        }
    }

    private function validateSchedule(): void
    {
        $startsAt = $this->toStartsAt();
        $endsAt = $this->toEndsAt();

        if ($endsAt !== null && $endsAt <= $startsAt) {
            throw InvalidAppointmentSchedule::inverted();
        }
    }

    private function validateNotes(): void
    {
        $this->toNotes();
    }
}
