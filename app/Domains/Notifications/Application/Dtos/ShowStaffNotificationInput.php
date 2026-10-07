<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Application\Dtos;

use App\Domains\Notifications\Exceptions\StaffNotificationNotFound;
use App\Domains\Notifications\ValueObjects\Identifier;

final readonly class ShowStaffNotificationInput
{
    public function __construct(
        public string $accountId,
        public string $notificationId,
    ) {}

    /**
     * @throws StaffNotificationNotFound
     */
    public function validate(): void
    {
        $this->validateNotificationId();
    }

    private function validateNotificationId(): void
    {
        if (! Identifier::isWellFormed($this->notificationId)) {
            throw StaffNotificationNotFound::withId($this->notificationId);
        }
    }
}
