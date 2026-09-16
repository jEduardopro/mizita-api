<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ServiceRequiresStaff extends DomainException implements DomainFailure
{
    public static function none(): self
    {
        return new self('A service must be performed by at least one staff member.');
    }

    public function errorCode(): string
    {
        return 'service_requires_staff';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
