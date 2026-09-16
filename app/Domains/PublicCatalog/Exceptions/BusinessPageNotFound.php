<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class BusinessPageNotFound extends RuntimeException implements DomainFailure
{
    public static function withSlug(string $slug, ?Throwable $previous = null): self
    {
        return new self("No booking page answers to [{$slug}].", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'business_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
