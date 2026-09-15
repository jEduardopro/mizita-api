<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnknownStaffMember extends DomainException implements DomainFailure
{
    public static function amongSelected(): self
    {
        return new self('One of the selected staff members is not assignable to this service.');
    }

    public function errorCode(): string
    {
        return 'unknown_staff_member';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
