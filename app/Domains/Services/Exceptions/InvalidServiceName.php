<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidServiceName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A service name cannot be empty.');
    }

    public static function tooShort(): self
    {
        return new self('The name offered is shorter than a service name may be.');
    }

    public static function tooLong(): self
    {
        return new self('The name offered is longer than a service name may be.');
    }

    public function errorCode(): string
    {
        return 'invalid_service_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
