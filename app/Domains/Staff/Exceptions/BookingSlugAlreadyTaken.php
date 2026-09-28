<?php

declare(strict_types=1);

namespace App\Domains\Staff\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;
use Throwable;

final class BookingSlugAlreadyTaken extends DomainException implements DomainFailure
{
    public static function for(string $slug, ?Throwable $previous = null): self
    {
        return new self("A team member of this business already books under [{$slug}].", 0, $previous);
    }

    public function errorCode(): string
    {
        return 'booking_slug_taken';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
