<?php

declare(strict_types=1);

namespace App\Domains\PublicCatalog\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use RuntimeException;
use Throwable;

final class StaffBookingPageNotFound extends RuntimeException implements DomainFailure
{
    public static function withSlug(string $staffSlug, ?Throwable $previous = null): self
    {
        return new self("No team member books under [{$staffSlug}] on this booking page.", previous: $previous);
    }

    public function errorCode(): string
    {
        return 'staff_member_not_found';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::NotFound;
    }
}
