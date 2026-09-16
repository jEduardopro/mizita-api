<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class UnsupportedBookingPageImage extends DomainException implements DomainFailure
{
    public static function missing(): self
    {
        return new self('No image was offered.');
    }

    public static function ofType(string $mimeType): self
    {
        return new self("[{$mimeType}] is not a supported booking page image type.");
    }

    public function errorCode(): string
    {
        return 'unsupported_booking_page_image';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
