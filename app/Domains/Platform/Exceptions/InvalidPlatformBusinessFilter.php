<?php

declare(strict_types=1);

namespace App\Domains\Platform\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class InvalidPlatformBusinessFilter extends DomainException implements DomainFailure
{
    public static function searchTooLong(int $maximum): self
    {
        return new self("A business search may not run past {$maximum} characters.");
    }

    public static function unknownSort(string $sort): self
    {
        return new self("The business list cannot be sorted by [{$sort}].");
    }

    public static function unknownDirection(string $direction): self
    {
        return new self("The sort direction [{$direction}] is neither ascending nor descending.");
    }

    public static function pageOutOfRange(int $page): self
    {
        return new self("The page [{$page}] is not a page the business list can have.");
    }

    public static function perPageOutOfRange(int $perPage, int $maximum): self
    {
        return new self("A business list page holds between 1 and {$maximum} rows, got [{$perPage}].");
    }

    public function errorCode(): string
    {
        return 'invalid_platform_business_filter';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
