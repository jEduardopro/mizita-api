<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Contracts;

use App\Domains\Notifications\Entities\StaffNotification;
use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;

interface StaffNotificationRepository
{
    /**
     * @throws StaffNotificationNotFound
     */
    public function findForBusiness(string $businessId, string $id): StaffNotification;

    public function addOnce(StaffNotification $notification): void;

    public function addOrRefreshUnread(StaffNotification $notification): void;

    public function save(StaffNotification $notification): void;

    /**
     * @throws StaffNotificationNotFound
     */
    public function delete(string $businessId, string $id): void;
}
