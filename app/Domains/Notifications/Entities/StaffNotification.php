<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Entities;

use App\Domains\Notifications\Exceptions\NotificationNotAddressedToReader;
use App\Domains\Notifications\ValueObjects\NotificationType;
use DateTimeImmutable;

final class StaffNotification
{
    private function __construct(
        public readonly string $id,
        public readonly string $businessId,
        public readonly string $recipientStaffMemberId,
        public readonly NotificationType $type,
        public readonly ?string $appointmentId,
        private ?DateTimeImmutable $readAt,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(
        string $id,
        string $businessId,
        string $recipientStaffMemberId,
        NotificationType $type,
        ?string $appointmentId,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            recipientStaffMemberId: $recipientStaffMemberId,
            type: $type,
            appointmentId: $appointmentId,
            readAt: null,
            createdAt: $now,
        );
    }

    public static function restore(
        string $id,
        string $businessId,
        string $recipientStaffMemberId,
        NotificationType $type,
        ?string $appointmentId,
        ?DateTimeImmutable $readAt,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: $id,
            businessId: $businessId,
            recipientStaffMemberId: $recipientStaffMemberId,
            type: $type,
            appointmentId: $appointmentId,
            readAt: $readAt,
            createdAt: $createdAt,
        );
    }

    /**
     * @throws NotificationNotAddressedToReader
     */
    public function markAsReadBy(string $staffMemberId, DateTimeImmutable $now): void
    {
        if (! $this->isAddressedTo($staffMemberId)) {
            throw NotificationNotAddressedToReader::forStaffMember($this->id, $staffMemberId);
        }

        if (! $this->isUnread()) {
            return;
        }

        $this->readAt = $now;
    }

    public function isAddressedTo(string $staffMemberId): bool
    {
        return $this->recipientStaffMemberId === $staffMemberId;
    }

    public function isUnread(): bool
    {
        return $this->readAt === null;
    }

    public function readAt(): ?DateTimeImmutable
    {
        return $this->readAt;
    }
}
