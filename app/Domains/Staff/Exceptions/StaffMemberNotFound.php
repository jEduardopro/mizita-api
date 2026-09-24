<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class StaffMemberNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Staff member [{$id}] was not found.");
    }

    public static function forAccount(string $accountId, ?Throwable $previous = null): self
    {
        return new self("Account [{$accountId}] is not a staff member of this business.", 0, $previous);
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
