<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Contracts;

use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\ValueObjects\NotificationAudience;
use App\Domains\Notifications\ValueObjects\NotificationRecord;
use App\Domains\Notifications\ValueObjects\NotificationStatus;
use App\Shared\ValueObjects\Paginated;
use App\Shared\ValueObjects\Pagination;

interface NotificationFeed
{
    /**
     * @return Paginated<NotificationRecord>
     */
    public function newestFirst(
        string $businessId,
        NotificationAudience $audience,
        NotificationStatus $status,
        Pagination $pagination,
    ): Paginated;

    public function countUnread(string $businessId, NotificationAudience $audience): int;

    /**
     * @throws StaffNotificationNotFound
     */
    public function find(string $businessId, string $notificationId): NotificationRecord;
}
