<?php

declare(strict_types=1);

namespace App\Domains\Businesses\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

/**
 * The industry the caller chose is not one the catalog knows about.
 *
 * Invalid rather than NotFound: the missing row is not the resource the request
 * names, it is one of the values that request carries, so this is the same
 * class of failure as a malformed field.
 */
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
