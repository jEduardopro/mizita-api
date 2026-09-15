<?php

declare(strict_types=1);

namespace App\Domains\Services\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidServiceBuffer extends DomainException implements DomainFailure
{
    public static function negative(int $minutes): self
    {
        return new self("[{$minutes}] is not a length of time a buffer may take.");
    }

    public static function tooLong(int $minutes): self
    {
        return new self("[{$minutes}] is longer than a service buffer may last.");
    }

    public function errorCode(): string
    {
        return 'invalid_service_buffer';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
