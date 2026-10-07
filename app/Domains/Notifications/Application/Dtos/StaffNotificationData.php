<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use DateTimeImmutable;

final readonly class StaffNotificationData
{
    /**
     * @param  array<string, array<string, string>>  $details
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?DateTimeImmutable $readAt,
        public DateTimeImmutable $createdAt,
        public bool $canMarkAsRead,
        public NotificationRecipientData $recipient,
        public array $details,
    ) {}

    public static function forReader(NotificationRecord $record, NotificationReader $reader): self
    {
        return new self(
            id: $record->id,
            type: $record->type()->value,
            readAt: $record->readAt,
            createdAt: $record->createdAt,
            canMarkAsRead: $record->canBeMarkedAsReadBy($reader),
            recipient: NotificationRecipientData::fromRecipient($record->recipient),
            details: $record->details(),
        );
    }
}
