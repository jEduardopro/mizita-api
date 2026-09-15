<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class ServiceNameAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $value, ?Throwable $previous = null): self
    {
        return new self("A service with name [{$value}] already exists.", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'service_name_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
