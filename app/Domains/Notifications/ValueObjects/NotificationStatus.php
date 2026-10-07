<?php

declare(strict_types=1);

namespace App\Domains\Notifications\ValueObjects;

enum NotificationStatus: string
{
    case Unread = 'unread';

    case All = 'all';

    public function excludesRead(): bool
    {
        return $this === self::Unread;
    }
}
