<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use InvalidArgumentException;

final class InvalidNotificationStatus extends InvalidArgumentException implements DomainFailure
{
    public static function unknown(string $status): self
    {
        return new self("Notification status [{$status}] is not supported.");
    }

    public function errorCode(): string
    {
        return 'invalid_notification_status';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
