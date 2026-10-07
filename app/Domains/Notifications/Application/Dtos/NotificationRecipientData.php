<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\ValueObjects\NotificationRecipient;

final readonly class NotificationRecipientData
{
    public function __construct(
        public string $id,
        public string $name,
    ) {}

    public static function fromRecipient(NotificationRecipient $recipient): self
    {
        return new self(
            id: $recipient->staffMemberId,
            name: $recipient->name,
        );
    }
}
