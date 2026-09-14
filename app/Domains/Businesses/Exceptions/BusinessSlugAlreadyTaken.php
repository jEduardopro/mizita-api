<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class BusinessSlugAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $value, ?Throwable $previous = null): self
    {
        return new self("A business with slug [{$value}] already exists.", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'business_slug_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
