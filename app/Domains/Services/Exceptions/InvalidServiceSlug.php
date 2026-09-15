<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidServiceSlug extends DomainException implements DomainFailure
{
    public static function forValue(string $value): self
    {
        return new self("[{$value}] is not a valid service web address.");
    }

    public function errorCode(): string
    {
        return 'invalid_service_slug';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
