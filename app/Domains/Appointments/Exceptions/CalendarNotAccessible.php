<?php

declare(strict_types=1);

namespace App\Domains\Appointments\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class CalendarNotAccessible extends DomainException implements DomainFailure
{
    public static function forAccount(string $accountId, ?Throwable $previous = null): self
    {
        return new self("Account [{$accountId}] is not a staff member of this business.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'business_not_accessible';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Forbidden;
    }
}
