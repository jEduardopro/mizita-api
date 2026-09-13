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

    public function errorCode(): string
    {
        return 'invalid_business_name';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
