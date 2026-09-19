<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Entities;

use App\Domains\Appointments\Exceptions\AppointmentAlreadyCancelled;
use App\Domains\Appointments\Exceptions\AppointmentAlreadyStarted;
use App\Domains\Appointments\ValueObjects\AppointmentNotes;
use App\Domains\Appointments\ValueObjects\AppointmentSlot;
use App\Domains\Appointments\ValueObjects\BookingSource;
use App\Domains\Appointments\ValueObjects\CancellationRule;
use App\Domains\Appointments\ValueObjects\Canceller;
use App\Domains\Appointments\ValueObjects\ManageToken;
use App\Domains\Appointments\ValueObjects\ReferenceCode;
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
        private ?ReferenceCode $referenceCode,
        private ?string $manageTokenHash,
        private ?DateTimeImmutable $manageTokenExpiresAt,
        private ?DateTimeImmutable $cancelledAt,
        private ?Canceller $cancelledBy,
        private BookingSource $source,
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
            referenceCode: null,
            manageTokenHash: null,
            manageTokenExpiresAt: null,
            cancelledAt: null,
            cancelledBy: null,
            source: BookingSource::Admin,
        );
    }

    public static function bookAsGuest(
        string $id,
        string $businessId,
        string $customerId,
        string $serviceId,
        string $staffMemberId,
        AppointmentSlot $slot,
        ?AppointmentNotes $notes,
        ReferenceCode $referenceCode,
        string $manageTokenHash,
        DateTimeImmutable $manageTokenExpiresAt,
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
            referenceCode: $referenceCode,
            manageTokenHash: $manageTokenHash,
            manageTokenExpiresAt: $manageTokenExpiresAt,
            cancelledAt: null,
            cancelledBy: null,
            source: BookingSource::Public,
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
        ?ReferenceCode $referenceCode = null,
        ?string $manageTokenHash = null,
        ?DateTimeImmutable $manageTokenExpiresAt = null,
        ?DateTimeImmutable $cancelledAt = null,
        ?Canceller $cancelledBy = null,
        BookingSource $source = BookingSource::Admin,
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
            referenceCode: $referenceCode,
            manageTokenHash: $manageTokenHash,
            manageTokenExpiresAt: $manageTokenExpiresAt,
            cancelledAt: $cancelledAt,
            cancelledBy: $cancelledBy,
            source: $source,
        );
    }

    /**
     * @throws AppointmentAlreadyCancelled
     * @throws AppointmentAlreadyStarted
     */
    public function rescheduleTo(AppointmentSlot $slot, DateTimeImmutable $now): void
    {
        $this->ensureStillOpen($now);

        $this->slot = $slot;
    }

    /**
     * @throws AppointmentAlreadyCancelled
     * @throws AppointmentAlreadyStarted
     */
    public function rescheduleAsGuest(
        AppointmentSlot $slot,
        DateTimeImmutable $manageTokenExpiresAt,
        DateTimeImmutable $now,
    ): void {
        $this->ensureStillOpen($now);

        $this->slot = $slot;
        $this->manageTokenExpiresAt = $manageTokenExpiresAt;
    }

    /**
     * @throws AppointmentAlreadyCancelled
     * @throws AppointmentAlreadyStarted
     */
    public function cancel(Canceller $by, DateTimeImmutable $now): void
    {
        $this->ensureStillOpen($now);

        $this->cancelledAt = $now;
        $this->cancelledBy = $by;
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

    public function assignReferenceCode(ReferenceCode $referenceCode): void
    {
        $this->referenceCode = $referenceCode;
    }

    public function isCancelled(): bool
    {
        return $this->cancelledAt !== null;
    }

    public function allowsChangeUnder(CancellationRule $rule, DateTimeImmutable $now): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        return $rule->allowsChangeAt($this->slot->startsAt, $now);
    }

    public function hasValidManageToken(string $candidate, DateTimeImmutable $now): bool
    {
        if ($this->manageTokenHash === null || $this->manageTokenExpiresAt === null) {
            return false;
        }

        if ($now >= $this->manageTokenExpiresAt) {
            return false;
        }

        return ManageToken::matches($candidate, $this->manageTokenHash);
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

    public function referenceCode(): ?ReferenceCode
    {
        return $this->referenceCode;
    }

    public function manageTokenHash(): ?string
    {
        return $this->manageTokenHash;
    }

    public function manageTokenExpiresAt(): ?DateTimeImmutable
    {
        return $this->manageTokenExpiresAt;
    }

    public function cancelledAt(): ?DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function cancelledBy(): ?Canceller
    {
        return $this->cancelledBy;
    }

    public function source(): BookingSource
    {
        return $this->source;
    }

    /**
     * @throws AppointmentAlreadyCancelled
     * @throws AppointmentAlreadyStarted
     */
    private function ensureStillOpen(DateTimeImmutable $now): void
    {
        if ($this->cancelledAt !== null) {
            throw AppointmentAlreadyCancelled::withId($this->id);
        }

        if ($now >= $this->slot->startsAt) {
            throw AppointmentAlreadyStarted::withId($this->id);
        }
    }
}
