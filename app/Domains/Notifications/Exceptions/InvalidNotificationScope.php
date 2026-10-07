<?php

declare(strict_types=1);

namespace App\Domains\Notifications\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use InvalidArgumentException;

final class InvalidNotificationScope extends InvalidArgumentException implements DomainFailure
{
    public static function unknown(string $scope): self
    {
        return new self("Notification scope [{$scope}] is not supported.");
    }

    public function errorCode(): string
    {
        return 'invalid_notification_scope';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
