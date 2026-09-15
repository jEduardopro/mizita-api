<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class ServiceNameNotSluggable extends DomainException implements DomainFailure
{
    public static function forName(string $name): self
    {
        return new self("[{$name}] cannot be turned into a service web address.");
    }

    public function errorCode(): string
    {
        return 'service_name_not_sluggable';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
