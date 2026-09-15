<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ServiceAlreadyInactive extends DomainException implements DomainFailure
{
    public static function for(string $id): self
    {
        return new self("Service [{$id}] is already inactive.");
    }

    public function errorCode(): string
    {
        return 'service_already_inactive';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
