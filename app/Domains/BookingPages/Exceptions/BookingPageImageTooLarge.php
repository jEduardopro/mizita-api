<?php

declare(strict_types=1);

namespace App\Domains\BookingPages\Exceptions;

use App\Shared\Contracts\DomainFailure;
use App\Shared\ValueObjects\DomainFailureKind;
use DomainException;

final class BookingPageImageTooLarge extends DomainException implements DomainFailure
{
    public static function atBytes(int $sizeInBytes, int $maximumBytes): self
    {
        return new self("A booking page image takes up to [{$maximumBytes}] bytes, got [{$sizeInBytes}].");
    }

    public function errorCode(): string
    {
        return 'booking_page_image_too_large';
    }

    public function kind(): DomainFailureKind
    {
        return DomainFailureKind::Invalid;
    }
}
