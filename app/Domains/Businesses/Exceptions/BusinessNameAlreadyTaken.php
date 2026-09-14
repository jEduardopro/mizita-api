<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class BusinessNameAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $name, ?Throwable $previous = null): self
    {
        return new self("A business named [{$name}] already exists.", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'business_name_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
