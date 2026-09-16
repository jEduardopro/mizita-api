<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;

final class BusinessNotFound extends RuntimeException implements DomainFailure
{
    public static function withId(string $id): self
    {
        return new self("Business [{$id}] was not found.");
    }

    public static function withSlug(string $slug): self
    {
        return new self("Business [{$slug}] was not found.");
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
