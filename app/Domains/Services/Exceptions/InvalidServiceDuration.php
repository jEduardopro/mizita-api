<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidServiceDuration extends DomainException implements DomainFailure
{
    public static function tooShort(int $minutes): self
    {
        return new self("[{$minutes}] is shorter than a service may last.");
    }

    public static function tooLong(int $minutes): self
    {
        return new self("[{$minutes}] is longer than a service may last.");
    }

    public function errorCode(): string
    {
        return 'invalid_service_duration';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
