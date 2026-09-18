<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Entities;

use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use DateTimeImmutable;

final class Appointment
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        private string $customerId,
        private string $serviceId,
        private string $staffMemberId,
        private AppointmentSlot $slot,
        private ?AppointmentNotes $notes,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        string $businessId,
        string $customerId,
        string $serviceId,
        string $staffMemberId,
        AppointmentSlot $slot,
        ?AppointmentNotes $notes,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            customerId: $customerId,
            serviceId: $serviceId,
            staffMemberId: $staffMemberId,
            slot: $slot,
            notes: $notes,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $customerId,
        string $serviceId,
        string $staffMemberId,
        AppointmentSlot $slot,
        ?AppointmentNotes $notes,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            customerId: $customerId,
            serviceId: $serviceId,
            staffMemberId: $staffMemberId,
            slot: $slot,
            notes: $notes,
            createdAt: $createdAt,
        );
    }

    public function reschedule(AppointmentSlot $slot): void
    {
        $this->slot = $slot;
    }

    public function changeNotes(?AppointmentNotes $notes): void
    {
        $this->notes = $notes;
    }

    public function changeCustomer(string $customerId): void
    {
        $this->customerId = $customerId;
    }

    public function changeService(string $serviceId): void
    {
        $this->serviceId = $serviceId;
    }

    public function reassign(string $staffMemberId): void
    {
        $this->staffMemberId = $staffMemberId;
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    public function serviceId(): string
    {
        return $this->serviceId;
    }

    public function staffMemberId(): string
    {
        return $this->staffMemberId;
    }

    public function slot(): AppointmentSlot
    {
        return $this->slot;
    }

    public function notes(): ?AppointmentNotes
    {
        return $this->notes;
    }
}
