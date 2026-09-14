<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidBusinessName extends DomainException implements DomainFailure
{
    public static function empty(): self
    {
        return new self('A business name cannot be empty.');
    }

    public static function tooShort(string $name): self
    {
        return new self("[{$name}] is too short for a business name.");
    }

    public static function tooLong(string $name): self
    {
        return new self("[{$name}] is too long for a business name.");
    }

    public function errorCode(): string
    {
        return 'invalid_business_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
