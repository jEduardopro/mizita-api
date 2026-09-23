<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Application\Dtos;

use App\Domains\Appointments\Exceptions\AppointmentServiceNotFound;
use App\Domains\Appointments\Exceptions\AppointmentStaffNotFound;
use App\Domains\Appointments\Exceptions\InvalidAppointmentNotes;
use App\Domains\Appointments\Exceptions\InvalidAppointmentSchedule;
use App\Domains\Appointments\Exceptions\InvalidGuestAddress;
use App\Domains\Appointments\Exceptions\InvalidGuestEmail;
use App\Domains\Appointments\Exceptions\InvalidGuestName;
use App\Domains\Appointments\Exceptions\InvalidGuestPhone;
use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Domains\Appointments\ValueObjects\Identifier;
use App\Domains\Appointments\ValueObjects\ScheduleInstant;
use DateTimeImmutable;

final readonly class BookAppointmentAsGuestInput
{
    public function __construct(
        public string $businessId,
        public string $serviceId,
        public string $staffMemberId,
        public string $startsAt,
        public GuestDetailsInput $guest,
        public ?string $notes,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function fromRequest(array $payload, string $businessId): self
    {
        return new self(
            businessId: $businessId,
            serviceId: self::textOrEmpty($payload['service_id'] ?? null),
            staffMemberId: self::textOrEmpty($payload['staff_member_id'] ?? null),
            startsAt: self::textOrEmpty($payload['starts_at'] ?? null),
            guest: GuestDetailsInput::fromPayload($payload['guest'] ?? null),
            notes: self::textOrNull($payload['notes'] ?? null),
        );
    }

    /**
     * @throws AppointmentServiceNotFound
     * @throws AppointmentStaffNotFound
     * @throws InvalidAppointmentSchedule
     * @throws InvalidAppointmentNotes
     * @throws InvalidGuestName
     * @throws InvalidGuestEmail
     * @throws InvalidGuestPhone
     * @throws InvalidGuestAddress
     */
    public function validate(): void
    {
        $this->validateServiceId();
        $this->validateStaffMemberId();
        $this->validateStartsAt();
        $this->validateNotes();
        $this->guest->validate();
    }

    /**
     * @throws InvalidAppointmentSchedule
     */
    public function toStartsAt(): DateTimeImmutable
    {
        return ScheduleInstant::fromString($this->startsAt)->value;
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

    private function validateStartsAt(): void
    {
        $this->toStartsAt();
    }

    private function validateNotes(): void
    {
        $this->toNotes();
    }
}
