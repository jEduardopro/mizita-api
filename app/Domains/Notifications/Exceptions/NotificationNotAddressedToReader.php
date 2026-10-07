<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class NotificationNotAddressedToReader extends DomainException implements DomainFailure
{
    public static function forStaffMember(string $notificationId, string $staffMemberId): self
    {
        return new self("Staff notification [{$notificationId}] is not addressed to staff member [{$staffMemberId}].");
    }

    public function errorCode(): string
    {
        return 'notification_not_addressed_to_reader';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
