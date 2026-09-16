<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BookingPageGalleryFull extends DomainException implements DomainFailure
{
    public static function atLimit(int $maximumImages): self
    {
        return new self("A booking page gallery holds up to [{$maximumImages}] images.");
    }

    public function errorCode(): string
    {
        return 'booking_page_gallery_full';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Conflict;
    }
}
