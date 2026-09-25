<?php

declare(strict_types=1);

namespace App\Domains\Integrations\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class CalendarOwnerNotFound extends RuntimeException implements DomainFailure
{
    public static function forAccount(string $accountId, ?Throwable $previous = null): self
    {
        return new self("Account [{$accountId}] is not a staff member of this business.", 0, $previous);
    }

    public static function withId(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] was not found.");
    }

    public function errorCode(): string
    {
        return 'staff_member_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
