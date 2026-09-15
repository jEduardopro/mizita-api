<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class ServiceNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Service [{$id}] was not found.");
    }

    public function errorCode(): string
    {
        return 'service_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
