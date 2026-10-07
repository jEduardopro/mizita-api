<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

use DateTimeImmutable;

final readonly class NotificationRecord
{
    public function __construct(
        public string $id,
        public NotificationType $type,
        public NotificationRecipient $recipient,
        public ?NotifiedAppointment $appointment,
        public ?NotifiedCustomer $customer,
        public ?DateTimeImmutable $readAt,
        public DateTimeImmutable $createdAt,
    ) {}

    public function isVisibleTo(NotificationReader $reader): bool
    {
        return $reader->canView($this->recipient->staffMemberId);
    }

    public function canBeMarkedAsReadBy(NotificationReader $reader): bool
    {
        return $this->readAt === null && $reader->isRecipient($this->recipient->staffMemberId);
    }
}
