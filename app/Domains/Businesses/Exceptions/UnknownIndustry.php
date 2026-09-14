<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnknownIndustry extends DomainException implements DomainFailure
{
    public static function withId(string $industryId): self
    {
        return new self("Industry [{$industryId}] is not in the catalog.");
    }

    public function errorCode(): string
    {
        return 'unknown_industry';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
