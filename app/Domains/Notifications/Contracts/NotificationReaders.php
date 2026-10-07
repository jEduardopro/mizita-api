<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Contracts;

use App\Domains\Notifications\Exceptions\NotificationsNotAccessible;
use App\Domains\Notifications\ValueObjects\NotificationReader;

interface NotificationReaders
{
    /**
     * @throws NotificationsNotAccessible
     */
    public function readerFor(string $businessId, string $accountId): NotificationReader;
}
