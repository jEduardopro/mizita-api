<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Infrastructure\Gateways;

use App\Domains\Notifications\Contracts\NotificationReaders;
use App\Domains\Notifications\Exceptions\NotificationsNotAccessible;
use App\Domains\Notifications\ValueObjects\NotificationReader;
use App\Domains\Staff\Contracts\StaffMemberRepository;
use App\Domains\Staff\Exceptions\StaffMemberNotFound;
use App\Domains\Staff\ValueObjects\StaffRole;
use App\Shared\Contracts\BusinessAuthorization;

final class StaffNotificationReaders implements NotificationReaders
{
    public function __construct(
        private readonly StaffMemberRepository $members,
        private readonly BusinessAuthorization $authorization,
    ) {}

    public function readerFor(string $businessId, string $accountId): NotificationReader
    {
        $staffMemberId = $this->staffMemberIdOf($businessId, $accountId);

        if ($this->ownsBusiness($businessId, $accountId)) {
            return NotificationReader::owner($staffMemberId);
        }

        return NotificationReader::member($staffMemberId);
    }

    /**
     * @throws NotificationsNotAccessible
     */
    private function staffMemberIdOf(string $businessId, string $accountId): string
    {
        try {
            return $this->members->findForAccount($businessId, $accountId)->id;
        } catch (StaffMemberNotFound $missing) {
            throw NotificationsNotAccessible::forAccount($accountId, $missing);
        }
    }

    private function ownsBusiness(string $businessId, string $accountId): bool
    {
        $roles = $this->authorization->grantsFor($accountId, $businessId)['roles'];

        return in_array(StaffRole::Owner->value, $roles, strict: true);
    }
}
