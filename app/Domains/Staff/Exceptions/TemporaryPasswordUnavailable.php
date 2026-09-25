<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class TemporaryPasswordUnavailable extends DomainException implements DomainFailure
{
    public static function for(string $staffMemberId): self
    {
        return new self("Staff member [{$staffMemberId}] has no temporary password left to reveal.");
    }

    public function errorCode(): string
    {
        return 'temporary_password_unavailable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
