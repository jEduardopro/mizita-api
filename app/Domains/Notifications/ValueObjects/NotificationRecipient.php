<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

final readonly class NotificationRecipient
{
    public function __construct(
        public string $staffMemberId,
        public string $name,
    ) {}
}
